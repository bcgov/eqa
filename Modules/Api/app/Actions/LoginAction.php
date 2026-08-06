<?php

declare(strict_types=1);

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * LoginAction
 *
 * Migrated from: EQA API/SQL Queries/Portal/Login.sql
 *
 * Resolves a portal user's login/session context by joining the portal
 * profile, account, portal security, and contact records for a given
 * user/business combination. Mirrors the original filtered SQL views
 * (Filteredeqa_portalprofile, FilteredAccount, Filteredeqa_portalsecurity,
 * FilteredContact) used by the legacy Dynamics SiteMinder login flow.
 */
final class LoginAction
{
    /**
     * @param string      $smUserGuid            SiteMinder user GUID (sm_userguid)
     * @param string      $smGovBusinessGuid      SiteMinder government business GUID (smgov_businessguid)
     * @param string|null $smUser                 SiteMinder user identifier (sm_user)
     * @param string|null $smGovBusinessLegalName Government business legal name (smgov_businesslegalname)
     *
     * @return array<string, mixed>|null Portal login context, or null when no matching profile is found.
     */
    public function __invoke(
        string $smUserGuid,
        string $smGovBusinessGuid,
        ?string $smUser = null,
        ?string $smGovBusinessLegalName = null,
    ): ?array {
        if (! Str::isUuid($smUserGuid) || ! Str::isUuid($smGovBusinessGuid)) {
            return null;
        }

        $result = DB::table('eqa_portalprofiles as profile')
            ->join('accounts as account', 'account.id', '=', 'profile.account_id')
            ->join('eqa_portalsecurities as security', 'security.portal_profile_id', '=', 'profile.id')
            ->join('contacts as contact', 'contact.id', '=', 'profile.contact_id')
            ->where('profile.sm_userguid', $smUserGuid)
            ->where('account.smgov_businessguid', $smGovBusinessGuid)
            ->when($smUser !== null, fn ($query) => $query->where('profile.sm_user', $smUser))
            ->when(
                $smGovBusinessLegalName !== null,
                fn ($query) => $query->where('account.smgov_businesslegalname', $smGovBusinessLegalName)
            )
            ->select([
                'profile.id as portal_profile_id',
                'profile.sm_userguid',
                'profile.sm_user',
                'account.id as account_id',
                'account.smgov_businessguid',
                'account.smgov_businesslegalname',
                'security.id as portal_security_id',
                'security.role as security_role',
                'security.status as security_status',
                'contact.id as contact_id',
                'contact.firstname',
                'contact.lastname',
                'contact.emailaddress1 as email',
            ])
            ->first();

        return $result !== null ? (array) $result : null;
    }
}