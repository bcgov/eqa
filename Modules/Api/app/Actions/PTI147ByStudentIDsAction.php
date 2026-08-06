<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Retrieves student/account records filtered by a set of student IDs.
 *
 * Migrated from legacy SQL query source:
 * EQA API/SQL Queries/Portal/PTI147ByStudentIDs.sql
 * Tables: Filteredcatapult_student, FilteredAccount
 */
class PTI147ByStudentIDsAction
{
    /**
     * Execute the action.
     *
     * @param array<int, string|int> $studentIds
     * @return Collection
     */
    public function __invoke(array $studentIds): Collection
    {
        if (empty($studentIds)) {
            return collect();
        }

        return DB::table('Filteredcatapult_student as fcs')
            ->join('FilteredAccount as fa', 'fa.AccountId', '=', 'fcs.AccountId')
            ->whereIn('fcs.StudentId', $studentIds)
            ->select([
                'fcs.StudentId',
                'fcs.AccountId',
                'fcs.*',
                'fa.*',
            ])
            ->get();
    }
}