<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Retrieves the full list of institutions for the Portal.
 *
 * Migrated from: EQA API/SQL Queries/Portal/AllInstitutions.sql
 *
 * Source lineage:
 * - Connector: sql_query
 * - Kind: query
 * - Tables: FilteredAccount, Filteredcatapult_registration
 */
class AllInstitutionsAction
{
    /**
     * Execute the action.
     *
     * @return Collection
     */
    public function __invoke(): Collection
    {
        return DB::table('FilteredAccount as fa')
            ->leftJoin(
                'Filteredcatapult_registration as fcr',
                'fcr.catapult_accountid',
                '=',
                'fa.accountid'
            )
            ->select([
                'fa.accountid',
                'fa.name',
                'fa.accountnumber',
                'fa.address1_city',
                'fa.address1_stateorprovince',
                'fa.address1_postalcode',
                'fa.address1_country',
                'fa.telephone1',
                'fa.emailaddress1',
                'fa.statecode',
                'fa.statuscode',
                'fcr.catapult_registrationid',
                'fcr.catapult_name as registration_name',
                'fcr.catapult_status as registration_status',
            ])
            ->orderBy('fa.name')
            ->get();
    }
}