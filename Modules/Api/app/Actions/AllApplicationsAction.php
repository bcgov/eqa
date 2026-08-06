<?php

namespace Modules\Api\Actions;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Retrieves all application records for the Portal, mirroring the legacy
 * "AllApplications" SQL query executed against the Filteredcatapult_application view.
 *
 * Source lineage: EQA API/SQL Queries/Portal/AllApplications.sql
 */
class AllApplicationsAction
{
    /**
     * Execute the action.
     */
    public function __invoke(): Collection
    {
        return $this->query()->get();
    }

    /**
     * Build the underlying query against the filtered applications view.
     */
    protected function query(): Builder
    {
        return DB::table('filtered_catapult_application');
    }
}