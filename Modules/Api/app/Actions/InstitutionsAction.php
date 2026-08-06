<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Retrieves institution records by combining account, registration,
 * and accreditation data.
 *
 * Source lineage: EQA API/SQL Queries/Mock/Institutions.sql
 */
class InstitutionsAction
{
    /**
     * Execute the action.
     */
    public function __invoke(): Collection
    {
        return DB::table('filtered_accounts as account')
            ->leftJoin('filtered_catapult_registrations as registration', 'registration.account_id', '=', 'account.id')
            ->leftJoin('filtered_catapult_accreditations as accreditation', 'accreditation.account_id', '=', 'account.id')
            ->select([
                'account.id as account_id',
                'account.name as account_name',
                'registration.id as registration_id',
                'registration.status as registration_status',
                'accreditation.id as accreditation_id',
                'accreditation.status as accreditation_status',
            ])
            ->get();
    }
}