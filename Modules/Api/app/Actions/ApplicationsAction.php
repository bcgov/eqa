<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Retrieves catapult_application records for a given AccountID.
 *
 * Source lineage: EQA API/SQL Queries/Portal/Applications.sql
 */
class ApplicationsAction
{
    /**
     * Execute the action.
     *
     * @param  string  $accountId
     * @return \Illuminate\Support\Collection
     */
    public function __invoke(string $accountId): Collection
    {
        return DB::table('filteredcatapult_application')
            ->where('accountid', $accountId)
            ->get();
    }
}