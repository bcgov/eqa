<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Retrieves the application programs available for a given institution
 * (Dynamics "Account"), aggregating catapult_applicationprogram records
 * with their related work experience, program, NOC, and CIP details.
 *
 * Migrated from: EQA API/SQL Queries/Portal/ApplicationProgramsForInstitution.sql
 */
class ApplicationProgramsForInstitutionAction
{
    public function __invoke(string $accountId): Collection
    {
        return DB::table('catapult_applicationprogram as ap')
            ->select([
                'ap.catapult_applicationprogramid',
                'ap.catapult_name',
                'ap.catapult_accountid',
                'ap.statecode',
                'ap.statuscode',
                'program.catapult_programid',
                'program.catapult_name as program_name',
                'workexperience.catapult_workexperienceid',
                'workexperience.catapult_name as workexperience_name',
                'noc.catapult_nocid',
                'noc.catapult_name as noc_name',
                'noc.catapult_code as noc_code',
                'cip.catapult_cipid',
                'cip.catapult_name as cip_name',
                'cip.catapult_code as cip_code',
            ])
            ->leftJoin('catapult_program as program', 'program.catapult_programid', '=', 'ap.catapult_programid')
            ->leftJoin('catapult_workexperience as workexperience', 'workexperience.catapult_workexperienceid', '=', 'ap.catapult_workexperienceid')
            ->leftJoin('catapult_noc as noc', 'noc.catapult_nocid', '=', 'program.catapult_nocid')
            ->leftJoin('catapult_cip as cip', 'cip.catapult_cipid', '=', 'program.catapult_cipid')
            ->where('ap.catapult_accountid', '=', $accountId)
            ->where('ap.statecode', '=', 0)
            ->orderBy('ap.catapult_name')
            ->get();
    }
}