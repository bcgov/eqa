<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Retrieves the list of programs associated with a given institution (account).
 *
 * Source lineage: EQA API/SQL Queries/Portal/ProgramsForInstitution.sql
 * Tables: Filteredcatapult_program, Filteredaccount, Filteredcatapult_registration
 */
class ProgramsForInstitutionAction
{
    /**
     * Execute the action.
     *
     * @param string $accountId The institution (account) identifier.
     * @return Collection
     */
    public function __invoke(string $accountId): Collection
    {
        return DB::table('Filteredcatapult_program as program')
            ->join('Filteredcatapult_registration as registration', 'registration.catapult_program', '=', 'program.catapult_programid')
            ->join('Filteredaccount as account', 'account.accountid', '=', 'registration.catapult_institution')
            ->where('account.accountid', '=', $accountId)
            ->select([
                'program.catapult_programid',
                'program.catapult_name',
                'program.catapult_code',
                'program.statecode',
                'program.statuscode',
                'account.accountid',
                'account.name as institution_name',
                'registration.catapult_registrationid',
            ])
            ->distinct()
            ->get();
    }
}