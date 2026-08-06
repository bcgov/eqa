<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Retrieves student enrollment records for a given institution (account),
 * joining enrollment, account, program, location and registration data.
 *
 * Source lineage: EQA API/SQL Queries/Portal/InstitutionStudentEnrollments.sql
 */
class InstitutionStudentEnrollmentsAction
{
    /**
     * Execute the action.
     *
     * @param string $accountId
     * @return Collection
     */
    public function __invoke(string $accountId): Collection
    {
        return DB::connection('sqlsrv')
            ->table('filteredcatapult_studentenrollment as enrollment')
            ->join('dbo.FilteredAccount as account', 'account.accountid', '=', 'enrollment.catapult_accountid')
            ->join('Filteredcatapult_program as program', 'program.catapult_programid', '=', 'enrollment.catapult_programid')
            ->join('Filteredcatapult_location as location', 'location.catapult_locationid', '=', 'enrollment.catapult_locationid')
            ->join('Filteredcatapult_registration as registration', 'registration.catapult_registrationid', '=', 'enrollment.catapult_registrationid')
            ->where('account.accountid', '=', $accountId)
            ->select([
                'enrollment.catapult_studentenrollmentid',
                'enrollment.catapult_name',
                'enrollment.statuscode',
                'account.accountid',
                'account.name as account_name',
                'program.catapult_programid',
                'program.catapult_name as program_name',
                'location.catapult_locationid',
                'location.catapult_name as location_name',
                'registration.catapult_registrationid',
                'registration.catapult_name as registration_name',
            ])
            ->get();
    }
}