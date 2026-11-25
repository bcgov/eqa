<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Admin\Models\ApplicationCampusLocation;
use Modules\Admin\Models\PreviousOperatingName;

/**
 * The application's "Locations, Address for Service and Previous Names" block: the campus locations
 * entered on the application (its own list, independent of the institution's campuses), and the
 * operating names it used in the last 5 years (one-to-many from both the institution and the application).
 */
class ApplicationProfileService
{
    public const NAME_LOOKBACK_YEARS = 5;

    private const LOCATION_FIELDS = ['location_name', 'email', 'website', 'street1', 'street2', 'city', 'province', 'postal_code', 'country'];

    /**
     * Campus locations entered on the application, in the order they were entered.
     *
     * @return list<array<string, mixed>>
     */
    public function campusLocations(string $applicationCrmId): array
    {
        return ApplicationCampusLocation::where('application_crm_id', $applicationCrmId)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ApplicationCampusLocation $l): array => array_merge(
                $l->only(self::LOCATION_FIELDS),
                ['primary_location' => (bool) $l->primary_location],
            ))
            ->values()
            ->all();
    }

    /**
     * Replace the campus locations entered on the application. The institution's campuses are untouched.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function syncCampusLocations(string $applicationCrmId, array $rows): void
    {
        ApplicationCampusLocation::where('application_crm_id', $applicationCrmId)->delete();

        foreach (array_values($rows) as $i => $row) {
            $values = [];
            foreach (self::LOCATION_FIELDS as $field) {
                $value = isset($row[$field]) ? trim((string) $row[$field]) : '';
                $values[$field] = $value === '' ? null : $value;
            }
            ApplicationCampusLocation::create(array_merge($values, [
                'application_crm_id' => $applicationCrmId,
                'primary_location' => (bool) ($row['primary_location'] ?? false),
                'sort_order' => $i,
            ]));
        }
    }

    /** First day of the look-back window: names last used before it need not be listed. */
    public function lookbackStart(): string
    {
        return Carbon::now('America/Vancouver')->subYears(self::NAME_LOOKBACK_YEARS)->toDateString();
    }

    /**
     * Names declared on the application.
     *
     * @return list<array{name: string, used_from: string, used_to: ?string}>
     */
    public function previousNames(string $applicationCrmId): array
    {
        return $this->rows(PreviousOperatingName::where('application_crm_id', $applicationCrmId)->orderByDesc('used_from')->get());
    }

    /**
     * Prefill for a new application: the names the institution used in the last 5 years, once each.
     *
     * @return list<array{name: string, used_from: string, used_to: ?string}>
     */
    public function recentInstitutionNames(string $institutionCrmId): array
    {
        $names = PreviousOperatingName::where('institution_crm_id', $institutionCrmId)
            ->where(fn ($q) => $q->whereNull('used_to')->orWhere('used_to', '>=', $this->lookbackStart()))
            ->orderByDesc('used_from')
            ->get();

        return array_values(collect($this->rows($names))
            ->unique(fn (array $row): string => mb_strtolower($row['name']).'|'.$row['used_from'].'|'.$row['used_to'])
            ->all());
    }

    /**
     * Replace the names declared on the application (each also belongs to the institution).
     *
     * @param  list<array{name: string, used_from: string, used_to?: ?string}>  $rows
     */
    public function syncPreviousNames(string $applicationCrmId, string $institutionCrmId, array $rows): void
    {
        PreviousOperatingName::where('application_crm_id', $applicationCrmId)->delete();

        foreach ($rows as $row) {
            PreviousOperatingName::create([
                'institution_crm_id' => $institutionCrmId,
                'application_crm_id' => $applicationCrmId,
                'name' => trim($row['name']),
                'used_from' => $row['used_from'],
                'used_to' => ($row['used_to'] ?? null) ?: null,
            ]);
        }
    }

    /**
     * @param  Collection<int, PreviousOperatingName>  $names
     * @return list<array{name: string, used_from: string, used_to: ?string}>
     */
    private function rows(Collection $names): array
    {
        return $names->map(fn (PreviousOperatingName $n): array => [
            'name' => $n->name,
            'used_from' => $n->used_from->toDateString(),
            'used_to' => $n->used_to?->toDateString(),
        ])->values()->all();
    }
}
