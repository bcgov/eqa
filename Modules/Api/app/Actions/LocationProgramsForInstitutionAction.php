<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Retrieves location programs for a given institution (Account).
 *
 * Source lineage: EQA API/SQL Queries/Portal/LocationProgramsForInstitution.sql
 * Tables: catapult_locationprogram, catapult_location, catapult_program, account, catapult_registration
 */
class LocationProgramsForInstitutionAction
{
    /**
     * Execute the action.
     *
     * @param string $accountId The institution (Account) identifier.
     * @return Collection
     */
    public function __invoke(string $accountId): Collection
    {
        return DB::table('catapult_locationprogram as lp')
            ->join('catapult_location as loc', 'loc.id', '=', 'lp.catapult_locationid')
            ->join('catapult_program as prog', 'prog.id', '=', 'lp.catapult_programid')
            ->join('account as acc', 'acc.id', '=', 'loc.catapult_accountid')
            ->leftJoin('catapult_registration as reg', function ($join) {
                $join->on('reg.catapult_locationprogramid', '=', 'lp.id');
            })
            ->where('acc.id', '=', $accountId)
            ->select([
                'lp.id as location_program_id',
                'loc.id as location_id',
                'loc.name as location_name',
                'prog.id as program_id',
                'prog.name as program_name',
                'acc.id as account_id',
                'acc.name as account_name',
                'reg.id as registration_id',
                'reg.name as registration_name',
                'reg.statuscode as registration_status',
            ])
            ->orderBy('loc.name')
            ->orderBy('prog.name')
            ->get();
    }
}