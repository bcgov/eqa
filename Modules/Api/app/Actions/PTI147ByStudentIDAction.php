<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Retrieves PTI147 student/account data for a given student ID.
 *
 * Source lineage: EQA API/SQL Queries/Portal/PTI147ByStudentID.sql
 * Tables: Filteredcatapult_student, FilteredAccount
 */
class PTI147ByStudentIDAction
{
    /**
     * Execute the action.
     *
     * @param string $studentId
     * @return Collection
     */
    public function __invoke(string $studentId): Collection
    {
        return DB::table('Filteredcatapult_student as fcs')
            ->join('FilteredAccount as fa', 'fa.accountid', '=', 'fcs.catapult_accountid')
            ->select([
                'fcs.catapult_studentid',
                'fcs.catapult_name',
                'fcs.catapult_studentidnumber',
                'fcs.catapult_accountid',
                'fa.accountid',
                'fa.name as account_name',
            ])
            ->where('fcs.catapult_studentidnumber', '=', $studentId)
            ->get();
    }
}