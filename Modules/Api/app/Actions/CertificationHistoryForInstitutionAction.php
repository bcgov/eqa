<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Retrieves the certification history for a given institution (account).
 *
 * Source lineage: EQA API/SQL Queries/Portal/CertificationHistoryForInstitution.sql
 */
class CertificationHistoryForInstitutionAction
{
    /**
     * Execute the action.
     *
     * @param string $accountId
     * @return Collection
     */
    public function __invoke(string $accountId): Collection
    {
        return DB::table('Filteredcatapult_registration')
            ->where('AccountID', $accountId)
            ->get();
    }
}