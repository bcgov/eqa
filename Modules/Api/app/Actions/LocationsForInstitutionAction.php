<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Retrieves the locations associated with an institution (account), migrated
 * from the legacy Dynamics CRM SQL query:
 * "EQA API/SQL Queries/Portal/LocationsForInstitution.sql".
 *
 * Legacy source joined the filtered CRM views:
 *   - Filteredcatapult_location
 *   - FilteredAccount
 *   - Filteredcatapult_registration
 * filtered by the "accountid" parameter.
 */
class LocationsForInstitutionAction
{
    /**
     * Execute the action.
     *
     * @param  string  $accountId  The institution (account) identifier to filter locations by.
     * @return Collection
     */
    public function __invoke(string $accountId): Collection
    {
        return DB::table('catapult_locations as location')
            ->join('accounts as account', 'account.id', '=', 'location.account_id')
            ->leftJoin('catapult_registrations as registration', 'registration.location_id', '=', 'location.id')
            ->where('location.account_id', $accountId)
            ->select([
                'location.id',
                'location.name',
                'location.address_line_1',
                'location.address_line_2',
                'location.city',
                'location.state',
                'location.postal_code',
                'location.country',
                'account.id as institution_id',
                'account.name as institution_name',
                'registration.id as registration_id',
                'registration.status as registration_status',
            ])
            ->distinct()
            ->orderBy('location.name')
            ->get();
    }
}