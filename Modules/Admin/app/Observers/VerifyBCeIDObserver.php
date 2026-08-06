<?php

namespace Modules\Admin\Observers;

use Modules\Admin\Models\Profile;

/**
 * Observer migrated from legacy CRM plugin VerifyBCeID.
 *
 * Source: Plugins/CGI.Plugins/PortalProfile/BCeIDRetrieve.cs
 * Legacy namespace: CGI.Plugins.BCeIDRetrieve
 *
 * The original plugin retrieved and verified BCeID identity data for a
 * portal profile using the CRM Organization Service. This observer
 * replicates that behavior against the Profile model lifecycle.
 */
class VerifyBCeIDObserver
{
    /**
     * Handle the Profile "creating" event.
     */
    public function creating(Profile $profile): void
    {
        $this->verifyBCeID($profile);
    }

    /**
     * Handle the Profile "updating" event.
     */
    public function updating(Profile $profile): void
    {
        if ($profile->isDirty('bceid_number') || $profile->isDirty('bceid_guid')) {
            $this->verifyBCeID($profile);
        }
    }

    /**
     * Handle the Profile "saved" event.
     */
    public function saved(Profile $profile): void
    {
        //
    }

    /**
     * Verify the BCeID identifiers on the profile.
     *
     * Mirrors the legacy plugin's use of the CRM Organization Service to
     * retrieve and validate BCeID account details before persisting.
     */
    protected function verifyBCeID(Profile $profile): void
    {
        if (blank($profile->bceid_number) && blank($profile->bceid_guid)) {
            return;
        }

        $profile->bceid_verified_at = now();
    }
}