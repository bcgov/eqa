No file needed—just returning the artifact content directly.

<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;

/**
 * Retrieves due dates for condition and bylaw compliance items belonging to
 * a given account, mirroring the legacy "DueDates" SQL query
 * (EQA API/SQL Queries/Portal/DueDates.sql).
 */
class DueDatesAction
{
    /**
     * Execute the action.
     *
     * @param  int|string  $accountId  The account identifier (AccountID parameter).
     * @return \Illuminate\Support\Collection
     */
    public function __invoke($accountId)
    {
        $conditions = DB::table('catapult_condition as cc')
            ->select([
                DB::raw("'Condition' as record_type"),
                'cc.catapult_conditionid as record_id',
                'cc.catapult_name as name',
                'cc.catapult_duedate as due_date',
                'cc.catapult_accountid as account_id',
            ])
            ->where('cc.catapult_accountid', $accountId)
            ->whereNotNull('cc.catapult_duedate');

        $bylaws = DB::table('catapult_bylaws as cb')
            ->select([
                DB::raw("'Bylaw' as record_type"),
                'cb.catapult_bylawsid as record_id',
                'cb.catapult_name as name',
                'cb.catapult_duedate as due_date',
                'cb.catapult_accountid as account_id',
            ])
            ->where('cb.catapult_accountid', $accountId)
            ->whereNotNull('cb.catapult_duedate');

        return $conditions
            ->unionAll($bylaws)
            ->orderBy('due_date')
            ->get();
    }
}