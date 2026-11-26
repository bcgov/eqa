<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ExpressPayService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Models\Application;
use Modules\Admin\Services\ApplicationFeeService;
use Modules\Admin\Services\ApplicationProfileService;
use Modules\Admin\Services\WebsiteComplianceService;

/**
 * Ministry (IDIR) view over migrated EQA designation applications: a paginated,
 * server-side searchable list plus the single-application review screen.
 */
class ApplicationController extends Controller
{
    /** Sortable columns: request key => applications column. */
    private const SORTS = [
        'reference' => 'reference',
        'institution' => 'institution_name',
        'status' => 'status',
        'application_date' => 'application_date',
        'approved_date' => 'approved_date',
    ];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $sort = array_key_exists((string) $request->string('sort'), self::SORTS) ? (string) $request->string('sort') : 'application_date';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $column = self::SORTS[$sort];

        $applications = Application::query()
            ->with('invoice:crm_id,application_crm_id')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.mb_strtolower($search).'%';
                $query->where(function (Builder $q) use ($like): void {
                    $q->whereRaw('lower(reference) like ?', [$like])
                        ->orWhereRaw('lower(institution_name) like ?', [$like])
                        ->orWhereRaw('lower(status) like ?', [$like]);
                });
            })
            // Blanks last in either direction.
            ->orderByRaw("{$column} is null")
            ->orderBy($column, $direction)
            ->orderBy('crm_id')
            ->paginate(25)
            ->withQueryString()
            ->through(function (Application $application): Application {
                $application->setAttribute('invoice_crm_id', $application->invoice?->crm_id);
                $application->unsetRelation('invoice');

                return $application;
            });

        return Inertia::render('Admin/Applications', [
            'applications' => $applications,
            'filters' => ['search' => $search, 'sort' => $sort, 'direction' => $direction],
        ]);
    }

    public function review(string $crmId): Response
    {
        $application = Application::with('invoice')->findOrFail($crmId);

        return Inertia::render('Admin/ApplicationReview', [
            'application' => $application->makeHidden(['invoice']),
            'invoice' => $application->invoice,
            'invoices' => DB::table('invoices')->where('application_crm_id', $crmId)->orderBy('invoice_number')->get()
                ->each(fn (object $invoice) => $invoice->payment_under_review = app(ExpressPayService::class)->hasPaymentUnderReview((string) $invoice->crm_id)),
            'adminFeeQuote' => app(ApplicationFeeService::class)->adminFeeQuote(DB::table('applications')->where('crm_id', $crmId)->first()),
            'defaultChangesDueDate' => app(ApplicationFeeService::class)->defaultChangesDueDate(),
            'campusLocations' => app(ApplicationProfileService::class)->campusLocations($crmId),
            'previousNames' => app(ApplicationProfileService::class)->previousNames($crmId),
            'websiteCompliance' => app(WebsiteComplianceService::class)->copy($crmId),
        ]);
    }
}
