<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Retrieves portal profile records joined with account data for a given
 * institution/account identifier.
 *
 * Source lineage: EQA API/SQL Queries/Portal/PortalProfiles.sql
 * Tables: dbo.Filteredeqa_portalprofile, dbo.FilteredAccount
 */
class PortalProfilesAction
{
    /**
     * Execute the action.
     *
     * @param  string  $instid  The institution/account id parameter (maps to legacy @instid).
     * @return Collection
     */
    public function __invoke(string $instid): Collection
    {
        if (trim($instid) === '') {
            throw new InvalidArgumentException('The instid parameter is required.');
        }

        return $this->fetchPortalProfiles($instid);
    }

    /**
     * Fetch portal profiles for the given institution/account id.
     *
     * @param  string  $instid
     * @return Collection
     */
    protected function fetchPortalProfiles(string $instid): Collection
    {
        return collect(DB::select(
            <<<'SQL'
                select
                    pp.eqa_portalprofileid,
                    pp.eqa_name,
                    pp.eqa_accountid,
                    pp.statecode,
                    pp.statuscode,
                    pp.createdon,
                    pp.modifiedon,
                    a.accountid,
                    a.name as account_name,
                    a.accountnumber,
                    a.statecode as account_statecode
                from dbo.Filteredeqa_portalprofile as pp
                inner join dbo.FilteredAccount as a
                    on a.accountid = pp.eqa_accountid
                where a.accountid = :instid
            SQL,
            ['instid' => $instid]
        ));
    }
}