<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;

/**
 * Executes the PTI147 legacy SQL query (source: EQA API/SQL Queries/Portal/PTI147.sql)
 * against catapult_student and account records.
 */
class PTI147Action
{
    public function __invoke(array $parameters = []): \Illuminate\Support\Collection
    {
        return collect(
            DB::table('catapult_student as cs')
                ->join('account as a', 'a.id', '=', 'cs.account_id')
                ->select('cs.*', 'a.*')
                ->get()
        );
    }
}