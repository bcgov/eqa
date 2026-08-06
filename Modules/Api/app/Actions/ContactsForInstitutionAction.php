<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Retrieves the contacts associated with an institution (Account) by joining
 * the FilteredContact and FilteredConnection tables.
 *
 * Source lineage: EQA API/SQL Queries/Portal/ContactsForInstitution.sql
 */
class ContactsForInstitutionAction
{
    /**
     * Execute the action.
     *
     * @param string $accountId The Account ID (institution) to fetch contacts for.
     * @return Collection
     */
    public function __invoke(string $accountId): Collection
    {
        return DB::table('FilteredContact as c')
            ->join('FilteredConnection as conn', 'conn.connectionid', '=', 'c.contactid')
            ->where('conn.accountid', '=', $accountId)
            ->select([
                'c.contactid',
                'c.fullname',
                'c.emailaddress1',
                'c.telephone1',
                'c.jobtitle',
                'conn.accountid',
                'conn.connectionid',
                'conn.record1roleid',
                'conn.record2roleid',
            ])
            ->get();
    }
}