<?php

namespace Modules\App\Actions;

use App\Models\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Retrieves all applications submitted for a given institution.
 *
 * Migrated from: EQA API/SQL Queries/Portal/ApplicationsForInstitution.sql
 */
class ApplicationsForInstitutionAction
{
    /**
     * Execute the action.
     *
     * @param int $institutionId
     * @return Collection<int, Application>
     */
    public function __invoke(int $institutionId): Collection
    {
        return Application::query()
            ->where('institution_id', $institutionId)
            ->with(['institution', 'status', 'applicant'])
            ->orderByDesc('submitted_at')
            ->get();
    }

    /**
     * Build the underlying query for further composition (e.g. pagination, filtering).
     *
     * @param int $institutionId
     * @return Builder<Application>
     */
    public function query(int $institutionId): Builder
    {
        return Application::query()
            ->where('institution_id', $institutionId)
            ->with(['institution', 'status', 'applicant'])
            ->orderByDesc('submitted_at');
    }
}