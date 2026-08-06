<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Returns the list of programs (with course, application, application detail
 * and status information) available to a given institution ("account").
 *
 * Migrated from the legacy Dynamics CRM SQL query:
 * EQA API/SQL Queries/Portal/AppProgramsForInstitution.sql
 *
 * The original query selected across the Dynamics "Filtered*" security
 * views (Filteredcatapult_course, Filteredcatapult_status,
 * Filteredptib_applicationdetail, Filteredcatapult_application,
 * Filteredcatapult_program, Filteredaccount), all scoped by a single
 * @AccountID parameter representing the institution's account record.
 *
 * In the PostgreSQL target these map to their plain (un-filtered) table
 * equivalents, since row-level security is now handled by the application
 * layer rather than CRM security views.
 */
class AppProgramsForInstitutionAction
{
    /**
     * @param string $accountId The institution's account identifier (GUID/UUID).
     *
     * @return Collection<int, object>
     */
    public function __invoke(string $accountId): Collection
    {
        return DB::table('catapult_application as application')
            ->join('catapult_program as program', 'program.catapult_programid', '=', 'application.catapult_programid')
            ->join('catapult_course as course', 'course.catapult_courseid', '=', 'program.catapult_courseid')
            ->leftJoin('catapult_status as status', 'status.catapult_statusid', '=', 'application.catapult_statusid')
            ->leftJoin('ptib_applicationdetail as applicationdetail', 'applicationdetail.catapult_applicationid', '=', 'application.catapult_applicationid')
            ->join('account', 'account.accountid', '=', 'application.catapult_accountid')
            ->where('account.accountid', $accountId)
            ->select([
                'application.catapult_applicationid as application_id',
                'application.catapult_name as application_name',
                'application.createdon as application_created_on',
                'program.catapult_programid as program_id',
                'program.catapult_name as program_name',
                'course.catapult_courseid as course_id',
                'course.catapult_name as course_name',
                'status.catapult_statusid as status_id',
                'status.catapult_name as status_name',
                'applicationdetail.ptib_applicationdetailid as application_detail_id',
                'applicationdetail.ptib_name as application_detail_name',
                'account.accountid as account_id',
                'account.name as account_name',
            ])
            ->orderBy('program.catapult_name')
            ->orderBy('course.catapult_name')
            ->get();
    }
}