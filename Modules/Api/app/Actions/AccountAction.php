<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Retrieves a single Account record joined with its registration and
 * accreditation data, mirroring the legacy "Account.sql" filtered-view query
 * (EQA API/SQL Queries/Portal/Account.sql).
 */
class AccountAction
{
    /**
     * @param string $accountId The AccountID GUID used to filter the account.
     *
     * @return \Illuminate\Support\Collection
     */
    public function __invoke(string $accountId): Collection
    {
        return DB::table('FilteredAccount as fa')
            ->leftJoin('Filteredcatapult_registration as fcr', 'fcr.accountid', '=', 'fa.accountid')
            ->leftJoin('Filteredcatapult_accreditation as fca', 'fca.accountid', '=', 'fa.accountid')
            ->select([
                'fa.*',
                'fcr.catapult_registrationid',
                'fcr.catapult_name as registration_name',
                'fcr.statuscode as registration_statuscode',
                'fca.catapult_accreditationid',
                'fca.catapult_name as accreditation_name',
                'fca.statuscode as accreditation_statuscode',
            ])
            ->where('fa.accountid', '=', $accountId)
            ->get();
    }
}