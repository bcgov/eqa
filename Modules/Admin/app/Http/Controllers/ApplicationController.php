<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Models\Application;

/**
 * Ministry (IDIR) view over migrated EQA designation applications: a paginated,
 * server-side searchable list plus the single-application review screen.
 */
class ApplicationController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

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
            ->orderByDesc('application_date')
            ->paginate(25)
            ->withQueryString()
            ->through(function (Application $application): Application {
                $application->setAttribute('invoice_crm_id', $application->invoice?->crm_id);
                $application->unsetRelation('invoice');

                return $application;
            });

        return Inertia::render('Admin/Applications', [
            'applications' => $applications,
            'filters' => ['search' => $search],
        ]);
    }

    public function review(string $crmId): Response
    {
        $application = Application::with(['invoice', 'institution:crm_id,qa_met_through'])
            ->findOrFail($crmId);

        // PTIRU Standing is only relevant when the institution's quality assurance
        // is met through the Private Training designation, so surface QA Met Through
        // to let the review screen hide the PTIRU standing check otherwise.
        $qaMetThrough = $application->institution?->qa_met_through;

        return Inertia::render('Admin/ApplicationReview', [
            'application' => $application->makeHidden(['invoice', 'institution']),
            'invoice' => $application->invoice,
            'qaMetThrough' => $qaMetThrough,
        ]);
    }
}
