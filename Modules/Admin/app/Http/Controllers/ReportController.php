<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Models\Application;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ministry (IDIR) reports over designated institutions, their primary contacts
 * and applications. Each report is shown on screen and downloadable as CSV.
 */
class ReportController extends Controller
{
    private const REPORTS = [
        'designated-institutions' => [
            'title' => 'Designated Institutions',
            'description' => 'All designated institutions with QA Met Through, DLI #, address and primary contact.',
        ],
        'designation-expiry' => [
            'title' => 'Designated Institutions with Expiry Dates',
            'description' => 'Each designated institution and its current designation expiry date.',
        ],
        'de-designated' => [
            'title' => 'Institutions No Longer Designated',
            'description' => 'Institutions whose designation ended within a period of time.',
            'filters' => ['from', 'to'],
            'note' => 'Leave a date blank for an open-ended period. Institutions de-designated before the de-designation date was recorded show their designation expiry date.',
        ],
        'primary-contacts' => [
            'title' => 'Primary Contacts',
            'description' => 'Primary contact name and email address for each designated institution.',
        ],
        'active-applications' => [
            'title' => 'Active Applications',
            'description' => 'Institutions with an application that is still in progress.',
        ],
    ];

    public function index(): Response
    {
        return Inertia::render('Admin/Reports', ['reports' => $this->catalogue(), 'report' => null]);
    }

    public function show(Request $request, string $report): Response
    {
        abort_unless(isset(self::REPORTS[$report]), 404);
        $filters = $this->filters($request, $report);

        return Inertia::render('Admin/Reports', [
            'reports' => $this->catalogue(),
            'report' => ['key' => $report] + self::REPORTS[$report] + ['filters' => []] + $this->build($report, $filters),
            'filters' => (object) $filters,
        ]);
    }

    public function export(Request $request, string $report): StreamedResponse
    {
        abort_unless(isset(self::REPORTS[$report]), 404);
        $filters = $this->filters($request, $report);
        $data = $this->build($report, $filters);
        $keys = array_keys($data['columns']);

        return response()->streamDownload(function () use ($data, $keys): void {
            $out = fopen('php://output', 'w');
            // BOM so Excel opens accented names as UTF-8.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values($data['columns']), escape: '');
            foreach ($data['rows'] as $row) {
                fputcsv($out, array_map(fn (string $key): string => $this->csvCell($row[$key] ?? null), $keys), escape: '');
            }
            fclose($out);
        }, $report.'-'.$this->today().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return list<array{key: string, title: string, description: string}> */
    private function catalogue(): array
    {
        return array_map(
            fn (string $key): array => ['key' => $key, 'title' => self::REPORTS[$key]['title'], 'description' => self::REPORTS[$key]['description']],
            array_keys(self::REPORTS),
        );
    }

    /** @return array<string, string|null> */
    private function filters(Request $request, string $report): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), 'after_or_equal:from')],
        ]);

        $filters = [];
        foreach (self::REPORTS[$report]['filters'] ?? [] as $name) {
            $filters[$name] = $data[$name] ?? null;
        }

        return $filters;
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return array{columns: array<string, string>, rows: list<array<string, mixed>>}
     */
    private function build(string $report, array $filters): array
    {
        return match ($report) {
            'designated-institutions' => $this->designatedInstitutions(),
            'designation-expiry' => $this->designationExpiry(),
            'de-designated' => $this->deDesignated($filters['from'], $filters['to']),
            'primary-contacts' => $this->contactsReport(),
            'active-applications' => $this->activeApplications(),
        };
    }

    private function designatedInstitutions(): array
    {
        $institutions = $this->designated()->get();
        $contacts = $this->primaryContacts($institutions);

        return [
            'columns' => [
                'name' => 'Institution Name',
                'qa_met_through' => 'QA Met Through',
                'dli_number' => 'DLI #',
                'street' => 'Street Address',
                'city' => 'City',
                'province' => 'Province',
                'postal_code' => 'Postal Code',
                'contact_name' => 'Primary Contact',
                'contact_job_title' => 'Primary Contact Job Title',
                'contact_email' => 'Primary Contact Email',
            ],
            'rows' => $institutions->map(fn (object $i): array => [
                'institution_crm_id' => $i->crm_id,
                'name' => $i->name,
                'qa_met_through' => $i->qa_met_through,
                'dli_number' => $i->dli_number,
                'street' => implode(', ', array_filter([trim((string) $i->street1), trim((string) $i->street2)])),
                'city' => $i->city,
                'province' => $i->province,
                'postal_code' => $i->postal_code,
                'contact_name' => $contacts[$i->crm_id]['name'],
                'contact_job_title' => $contacts[$i->crm_id]['job_title'],
                'contact_email' => $contacts[$i->crm_id]['email'],
            ])->all(),
        ];
    }

    private function designationExpiry(): array
    {
        $institutions = $this->designated()
            ->reorder()
            ->orderByRaw('designation_expiry is null')
            ->orderBy('designation_expiry')
            ->orderBy('name')
            ->get(['crm_id', 'name', 'qa_met_through', 'designation_expiry']);

        return [
            'columns' => [
                'name' => 'Institution Name',
                'qa_met_through' => 'QA Met Through',
                'designation_expiry' => 'Current Expiry Date',
            ],
            'rows' => $institutions->map(fn (object $i): array => [
                'institution_crm_id' => $i->crm_id,
                'name' => $i->name,
                'qa_met_through' => $i->qa_met_through,
                'designation_expiry' => $i->designation_expiry,
            ])->all(),
        ];
    }

    /**
     * Institutions that are no longer Designated, dated by when the designation
     * ended. Migrated rows have no recorded de-designation date, so their
     * designation expiry stands in for it.
     */
    private function deDesignated(?string $from, ?string $to): array
    {
        $ended = 'COALESCE(de_designated_date, designation_expiry)';

        $institutions = DB::table('institutions')
            ->where(fn (Builder $q) => $q->whereNull('eqa_status')->orWhere('eqa_status', '<>', 'Designated'))
            ->where(fn (Builder $q) => $q->where('eqa_status', 'De-Designated')->orWhereNotNull('de_designated_date'))
            ->when($from, fn (Builder $q, string $date) => $q->whereRaw("{$ended} >= ?", [$date]))
            ->when($to, fn (Builder $q, string $date) => $q->whereRaw("{$ended} <= ?", [$date]))
            ->orderByRaw("{$ended} is null")
            ->orderByRaw("{$ended} desc")
            ->orderBy('name')
            ->selectRaw("crm_id, name, {$ended} as de_designated_date")
            ->get();

        return [
            'columns' => [
                'name' => 'Institution Name',
                'de_designated_date' => 'De-Designation Date',
            ],
            'rows' => $institutions->map(fn (object $i): array => [
                'institution_crm_id' => $i->crm_id,
                'name' => $i->name,
                'de_designated_date' => $i->de_designated_date,
            ])->all(),
        ];
    }

    private function contactsReport(): array
    {
        $institutions = $this->designated()->get(['crm_id', 'name', 'primary_contact']);
        $contacts = $this->primaryContacts($institutions);

        return [
            'columns' => [
                'first_name' => 'First Name',
                'last_name' => 'Last Name',
                'email' => 'Email Address',
                'name' => 'Institution Name',
            ],
            'rows' => $institutions->map(fn (object $i): array => [
                'institution_crm_id' => $i->crm_id,
                'first_name' => $contacts[$i->crm_id]['first_name'],
                'last_name' => $contacts[$i->crm_id]['last_name'],
                'email' => $contacts[$i->crm_id]['email'],
                'name' => $i->name,
            ])->all(),
        ];
    }

    private function activeApplications(): array
    {
        $applications = DB::table('applications as a')
            ->leftJoin('institutions as i', 'i.crm_id', '=', 'a.institution_crm_id')
            ->whereIn('a.workflow_stage', Application::ACTIVE_STAGES)
            ->orderByRaw('COALESCE(i.name, a.institution_name)')
            ->orderBy('a.application_date')
            ->selectRaw('a.crm_id, a.institution_crm_id, COALESCE(i.name, a.institution_name) as name, a.reference, a.status, a.application_date')
            ->get();

        return [
            'columns' => [
                'name' => 'Institution Name',
                'reference' => 'Application #',
                'status' => 'Current Application Status',
                'application_date' => 'Date Applied',
            ],
            'rows' => $applications->map(fn (object $a): array => [
                'institution_crm_id' => $a->institution_crm_id,
                'application_crm_id' => $a->crm_id,
                'name' => $a->name,
                'reference' => $a->reference,
                'status' => $a->status,
                'application_date' => $a->application_date,
            ])->all(),
        ];
    }

    private function designated(): Builder
    {
        return DB::table('institutions')->where('eqa_status', 'Designated')->orderBy('name');
    }

    /**
     * The contact named as each institution's primary contact. Without a named
     * primary contact, falls back to the institution's best portal contact
     * (active administrator with an email), as the notification emails do.
     *
     * @param  Collection<int, object>  $institutions
     * @return array<string, array{name: string, first_name: string, last_name: string, job_title: string, email: string}>
     */
    private function primaryContacts(Collection $institutions): array
    {
        $normalize = static fn (?string $name): string => mb_strtolower((string) preg_replace('/\s+/', ' ', trim((string) $name)));

        $users = DB::table('institution_users')
            ->whereIn('institution_crm_id', $institutions->pluck('crm_id')->all())
            ->orderByRaw("CASE WHEN email IS NULL OR email = '' THEN 1 ELSE 0 END")
            ->orderByRaw('CASE WHEN web_user_active THEN 0 ELSE 1 END')
            ->orderByRaw("CASE WHEN LOWER(COALESCE(role, '')) LIKE '%admin%' THEN 0 ELSE 1 END")
            ->orderBy('crm_id')
            ->get()
            ->groupBy('institution_crm_id');

        $contacts = [];
        foreach ($institutions as $institution) {
            $named = trim((string) $institution->primary_contact);
            $candidates = $users->get($institution->crm_id, collect());
            $user = $named === ''
                ? $candidates->first()
                : $candidates->first(fn (object $u): bool => in_array(
                    $normalize($named),
                    [$normalize($u->full_name), $normalize($u->first_name.' '.$u->last_name)],
                    true,
                ));

            if ($user !== null) {
                $first = trim((string) $user->first_name);
                $last = trim((string) $user->last_name);
                $name = $named !== '' ? $named : (trim((string) $user->full_name) ?: trim($first.' '.$last));
            } else {
                // Unmatched name: best-effort split into first / last.
                $parts = preg_split('/\s+/', $named) ?: [];
                $last = count($parts) > 1 ? (string) array_pop($parts) : '';
                $first = implode(' ', $parts);
                $name = $named;
            }

            $contacts[$institution->crm_id] = [
                'name' => $name,
                'first_name' => $first,
                'last_name' => $last,
                'job_title' => trim((string) ($user->job_title ?? '')),
                'email' => trim((string) ($user->email ?? '')),
            ];
        }

        return $contacts;
    }

    /** Neutralise spreadsheet formulas (CSV injection). */
    private function csvCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    private function today(): string
    {
        return Carbon::now('America/Vancouver')->toDateString();
    }
}
