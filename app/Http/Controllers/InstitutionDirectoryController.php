<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public directory of EQA-designated institutions, modelled on the PTIB
 * "Private Training Institution Directory". Only public business details are
 * exposed — never institution staff/contacts.
 */
class InstitutionDirectoryController extends Controller
{
    /** "Previously designated" matches the "No longer EQA-designated" section of the PDF registry. */
    private const STATUSES = [
        'designated' => ['Designated'],
        'previous' => ['De-Designated', 'Not Approved'],
    ];

    private const PER_PAGE = [10, 25, 50, 100];

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $institutions = $this->search($filters)
            ->orderBy('name')
            ->select(['crm_id', 'institution_number', 'name', 'street1', 'city', 'eqa_status'])
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (object $i): array => [
                'crm_id' => $i->crm_id,
                'institution_number' => $i->institution_number,
                'name' => $i->name,
                'street' => $this->firstLine($i->street1),
                'city' => $i->city,
                'status' => $i->eqa_status,
            ]);

        return Inertia::render('Public/InstitutionDirectory', [
            'filters' => $filters,
            'cities' => $this->cities(),
            'institutions' => $institutions,
            'institution' => null,
        ]);
    }

    public function show(Request $request, string $crmId): Response
    {
        $institution = DB::table('institutions')
            ->where('crm_id', $crmId)
            ->whereIn('eqa_status', array_merge(...array_values(self::STATUSES)))
            ->first();
        abort_if($institution === null, 404);

        return Inertia::render('Public/InstitutionDirectory', [
            'filters' => $this->filters($request),
            'cities' => $this->cities(),
            'institutions' => null,
            'institution' => [
                'crm_id' => $institution->crm_id,
                'institution_number' => $institution->institution_number,
                'name' => $institution->name,
                'status' => $institution->eqa_status,
                'designation_date' => $this->date($institution->designation_start),
                'de_designated_date' => in_array($institution->eqa_status, self::STATUSES['previous'], true)
                    ? $this->date(($institution->de_designated_date ?? null) ?: $institution->designation_expiry)
                    : null,
                'address' => $this->address($institution),
                'website' => $institution->website,
                'locations' => $this->related('campuses', $crmId, ['location_name', 'name', 'email', 'website'], 'primary_location')
                    ->map(fn (object $c): array => [
                        'name' => $c->location_name ?: $c->name ?: $institution->name,
                        'city' => trim(implode(', ', array_filter([$c->city, $c->province]))),
                        'address' => $this->address($c),
                        'email' => $c->email,
                        'website' => $c->website,
                    ])->all(),
                'dbas' => $this->related('dbas', $crmId, ['name', 'website'])
                    ->map(fn (object $d): array => [
                        'name' => $d->name,
                        'address' => $this->address($d),
                        'website' => $d->website,
                    ])->all(),
            ],
        ]);
    }

    /** @return array{status: string, q: string, city: string, per_page: int} */
    private function filters(Request $request): array
    {
        $status = (string) $request->query('status');
        $perPage = (int) $request->query('per_page');

        return [
            'status' => array_key_exists($status, self::STATUSES) ? $status : 'designated',
            'q' => trim(mb_substr((string) $request->query('q'), 0, 100)),
            'city' => trim(mb_substr((string) $request->query('city'), 0, 100)),
            'per_page' => in_array($perPage, self::PER_PAGE, true) ? $perPage : self::PER_PAGE[0],
        ];
    }

    /** @param  array{status: string, q: string, city: string, per_page: int}  $filters */
    private function search(array $filters): Builder
    {
        return DB::table('institutions')
            ->whereIn('eqa_status', self::STATUSES[$filters['status']])
            ->when($filters['q'] !== '', function (Builder $q) use ($filters): void {
                $like = '%'.$this->escapeLike(mb_strtolower($filters['q'])).'%';
                $q->where(function (Builder $q) use ($like): void {
                    $q->whereRaw("lower(name) like ? escape '!'", [$like])
                        ->orWhereRaw("lower(institution_number) like ? escape '!'", [$like]);
                    if (Schema::hasTable('dbas')) {
                        $q->orWhereExists(fn (Builder $d) => $d->from('dbas')
                            ->whereColumn('dbas.institution_crm_id', 'institutions.crm_id')
                            ->where('dbas.status', 'Active')
                            ->whereRaw("lower(dbas.name) like ? escape '!'", [$like]));
                    }
                });
            })
            ->when($filters['city'] !== '', function (Builder $q) use ($filters): void {
                $city = mb_strtolower($filters['city']);
                $q->where(function (Builder $q) use ($city): void {
                    $q->whereRaw('lower(trim(city)) = ?', [$city]);
                    if (Schema::hasTable('campuses')) {
                        $q->orWhereExists(fn (Builder $c) => $c->from('campuses')
                            ->whereColumn('campuses.institution_crm_id', 'institutions.crm_id')
                            ->where('campuses.status', 'Active')
                            ->whereRaw('lower(trim(campuses.city)) = ?', [$city]));
                    }
                });
            });
    }

    /** Cities of listed institutions and their active locations, for the "City" filter. @return array<int, string> */
    private function cities(): array
    {
        $listed = array_merge(...array_values(self::STATUSES));
        $cities = DB::table('institutions')->whereIn('eqa_status', $listed)->pluck('city');

        if (Schema::hasTable('campuses')) {
            $cities = $cities->merge(DB::table('campuses')
                ->join('institutions', 'institutions.crm_id', '=', 'campuses.institution_crm_id')
                ->whereIn('institutions.eqa_status', $listed)
                ->where('campuses.status', 'Active')
                ->pluck('campuses.city'));
        }

        return $cities->map(fn ($c) => trim((string) $c))->filter()
            ->unique(fn (string $c) => mb_strtolower($c))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()->all();
    }

    /**
     * Active campuses/DBAs for an institution.
     *
     * @param  array<int, string>  $columns
     * @return Collection<int, object>
     */
    private function related(string $table, string $crmId, array $columns, ?string $primaryColumn = null)
    {
        if (! Schema::hasTable($table)) {
            return collect();
        }

        return DB::table($table)
            ->where('institution_crm_id', $crmId)
            ->where('status', 'Active')
            ->when($primaryColumn, fn (Builder $q, string $col) => $q->orderByDesc($col))
            ->orderBy('name')
            ->get(array_merge($columns, ['street1', 'street2', 'city', 'province', 'postal_code', 'country']));
    }

    /** @return array<int, string> */
    private function address(object $row): array
    {
        $streets = collect([$row->street1 ?? null, $row->street2 ?? null, $row->street3 ?? null])
            ->flatMap(fn ($s) => preg_split('/\R/', trim((string) $s)) ?: []);
        $locality = implode(', ', array_filter([trim((string) ($row->city ?? '')), trim((string) ($row->province ?? ''))]));

        return $streets->push($locality, trim((string) ($row->postal_code ?? '')))
            ->map(fn ($line) => trim((string) $line))
            ->filter()->values()->all();
    }

    private function firstLine(?string $value): ?string
    {
        $line = trim((string) strtok((string) $value, "\r\n"));

        return $line === '' ? null : $line;
    }

    private function date(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('F j, Y') : null;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
