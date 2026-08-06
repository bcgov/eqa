<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Retrieves the list of suspensions joined with account, registration,
 * and accreditation data.
 *
 * Source lineage: EQA API/SQL Queries/Lists/Suspensions.sql
 */
class SuspensionsAction
{
    /**
     * Execute the action.
     */
    public function __invoke(): Collection
    {
        return DB::table('catapult_suspensions as suspension')
            ->join('accounts as account', 'account.id', '=', 'suspension.account_id')
            ->join('catapult_registrations as registration', 'registration.account_id', '=', 'account.id')
            ->leftJoin('catapult_accreditations as accreditation', 'accreditation.account_id', '=', 'account.id')
            ->whereNull('suspension.deleted_at')
            ->whereNull('account.deleted_at')
            ->whereNull('registration.deleted_at')
            ->select([
                'suspension.id as suspension_id',
                'suspension.catapult_name as suspension_name',
                'suspension.catapult_startdate as start_date',
                'suspension.catapult_enddate as end_date',
                'suspension.catapult_status as status',
                'suspension.catapult_reason as reason',
                'account.id as account_id',
                'account.name as account_name',
                'registration.id as registration_id',
                'registration.catapult_name as registration_name',
                'accreditation.id as accreditation_id',
                'accreditation.catapult_name as accreditation_name',
            ])
            ->orderByDesc('suspension.catapult_startdate')
            ->get();
    }
}