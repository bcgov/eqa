<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Retrieves the students belonging to an institution (account) by joining
 * student, student enrollment, account and registration records.
 *
 * Legacy source: EQA API/SQL Queries/Portal/InstitutionStudents.sql
 */
class InstitutionStudentsAction
{
    /**
     * Execute the action.
     *
     * @param  string  $accountId  The institution account identifier (AccountID parameter).
     * @return \Illuminate\Support\Collection
     */
    public function __invoke(string $accountId): Collection
    {
        return DB::table('filteredcatapult_student as student')
            ->join('filteredcatapult_studentenrollment as enrollment', 'enrollment.catapult_student', '=', 'student.catapult_studentid')
            ->join('dbo.FilteredAccount as account', 'account.accountid', '=', 'enrollment.catapult_institution')
            ->leftJoin('Filteredcatapult_registration as registration', 'registration.catapult_student', '=', 'student.catapult_studentid')
            ->where('account.accountid', '=', $accountId)
            ->select([
                'student.catapult_studentid',
                'student.catapult_name',
                'student.catapult_firstname',
                'student.catapult_lastname',
                'student.catapult_email',
                'enrollment.catapult_studentenrollmentid',
                'enrollment.catapult_status',
                'account.accountid',
                'account.name as institution_name',
                'registration.catapult_registrationid',
                'registration.catapult_status as registration_status',
            ])
            ->get();
    }
}