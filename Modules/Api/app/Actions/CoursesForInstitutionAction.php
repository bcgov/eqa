<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Retrieves the list of courses (with their parent program) associated with
 * a given institution (account).
 *
 * Migrated from: EQA API/SQL Queries/Portal/CoursesForInstitution.sql
 * Original source tables: Filteredcatapult_course, Filteredcatapult_program
 */
class CoursesForInstitutionAction
{
    /**
     * Execute the action.
     *
     * @param string $accountId The institution (account) identifier.
     * @return Collection
     */
    public function __invoke(string $accountId): Collection
    {
        return DB::table('catapult_course as course')
            ->join('catapult_program as program', 'program.id', '=', 'course.program_id')
            ->where('course.account_id', $accountId)
            ->select([
                'course.id',
                'course.name',
                'course.code',
                'course.status',
                'program.id as program_id',
                'program.name as program_name',
            ])
            ->orderBy('program.name')
            ->orderBy('course.name')
            ->get();
    }
}