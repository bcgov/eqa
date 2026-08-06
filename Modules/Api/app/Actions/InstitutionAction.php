<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Retrieves institution (Account) details for the EQA Portal, including
 * related location, work experience, program, registration, accreditation
 * and status records associated with a given Account.
 *
 * Migrated from: EQA API/SQL Queries/Portal/Institution.sql
 * Source tables: Filteredeqa_location, Filteredeqa_workexperience,
 *                Filteredeqa_program, FilteredAccount, Filteredeqa_registration,
 *                Filteredeqa_accreditation, Filteredeqa_status
 * Parameters: AccountID
 */
final class InstitutionAction
{
    /**
     * Execute the action.
     *
     * @param  string  $accountId  The Account (institution) identifier.
     * @return Collection
     */
    public function __invoke(string $accountId): Collection
    {
        return DB::table('accounts as account')
            ->select([
                'account.id as account_id',
                'account.name as account_name',
                'account.account_number',
                'account.status_code as account_status_code',
                'location.id as location_id',
                'location.name as location_name',
                'location.address_line_1',
                'location.address_line_2',
                'location.city',
                'location.state_province',
                'location.postal_code',
                'location.country',
                'work_experience.id as work_experience_id',
                'work_experience.name as work_experience_name',
                'program.id as program_id',
                'program.name as program_name',
                'registration.id as registration_id',
                'registration.registration_number',
                'registration.effective_date as registration_effective_date',
                'registration.expiration_date as registration_expiration_date',
                'accreditation.id as accreditation_id',
                'accreditation.name as accreditation_name',
                'accreditation.effective_date as accreditation_effective_date',
                'accreditation.expiration_date as accreditation_expiration_date',
                'status.id as status_id',
                'status.name as status_name',
            ])
            ->leftJoin('eqa_locations as location', 'location.account_id', '=', 'account.id')
            ->leftJoin('eqa_work_experiences as work_experience', 'work_experience.location_id', '=', 'location.id')
            ->leftJoin('eqa_programs as program', 'program.account_id', '=', 'account.id')
            ->leftJoin('eqa_registrations as registration', 'registration.account_id', '=', 'account.id')
            ->leftJoin('eqa_accreditations as accreditation', 'accreditation.account_id', '=', 'account.id')
            ->leftJoin('eqa_statuses as status', 'status.id', '=', 'account.status_id')
            ->where('account.id', '=', $accountId)
            ->get();
    }
}