<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Portalprofile
 *
 * Source lineage: EQADEV_MSCRM/eqa_portalprofileBase (sql_schema:table)
 */
class Portalprofile extends Model
{
    protected $table = 'portalprofile';

    protected $primaryKey = 'eqa_portalprofile_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'modified_on';

    protected $fillable = [
        'created_by',
        'modified_by',
        'created_on_behalf_by',
        'modified_on_behalf_by',
        'owner_id',
        'owner_id_type',
        'owning_business_unit',
        'statecode',
        'statuscode',
        'import_sequence_number',
        'overridden_created_on',
        'time_zone_rule_version_number',
        'utc_conversion_time_zone_code',
        'eqa_name',
        'eqa_account_dluid',
        'eqa_bceid',
        'eqa_business_dluid',
        'eqa_business_guid',
        'eqa_business_legal_name',
        'eqa_contact_id',
        'eqa_delegated_administrator',
        'eqa_email',
        'eqa_first_name',
        'eqa_institution_id',
        'eqa_lastlogon',
        'eqa_managed_disabled',
        'eqa_portal_security_id',
        'eqa_removed_from_bceid',
        'eqa_surname',
        'eqa_suspended',
        'eqa_terms_of_use',
        'eqa_user_guid',
        'eqa_contact_notified',
    ];

    protected $casts = [
        'eqa_portalprofile_id' => 'string',
        'created_on' => 'datetime',
        'created_by' => 'string',
        'modified_on' => 'datetime',
        'modified_by' => 'string',
        'created_on_behalf_by' => 'string',
        'modified_on_behalf_by' => 'string',
        'owner_id' => 'string',
        'owner_id_type' => 'integer',
        'owning_business_unit' => 'string',
        'statecode' => 'integer',
        'statuscode' => 'integer',
        'version_number' => 'integer',
        'import_sequence_number' => 'integer',
        'overridden_created_on' => 'datetime',
        'time_zone_rule_version_number' => 'integer',
        'utc_conversion_time_zone_code' => 'integer',
        'eqa_name' => 'string',
        'eqa_account_dluid' => 'string',
        'eqa_bceid' => 'string',
        'eqa_business_dluid' => 'string',
        'eqa_business_guid' => 'string',
        'eqa_business_legal_name' => 'string',
        'eqa_contact_id' => 'string',
        'eqa_delegated_administrator' => 'boolean',
        'eqa_email' => 'string',
        'eqa_first_name' => 'string',
        'eqa_institution_id' => 'string',
        'eqa_lastlogon' => 'datetime',
        'eqa_managed_disabled' => 'boolean',
        'eqa_portal_security_id' => 'string',
        'eqa_removed_from_bceid' => 'boolean',
        'eqa_surname' => 'string',
        'eqa_suspended' => 'boolean',
        'eqa_terms_of_use' => 'boolean',
        'eqa_user_guid' => 'string',
        'eqa_contact_notified' => 'boolean',
    ];

    /**
     * eqa_TodaysDate is a computed column on the source system; expose it
     * as a read-only accessor rather than a persisted/fillable attribute.
     */
    protected function eqaTodaysDate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['eqa_todays_date'] ?? null,
        );
    }

    /**
     * The portal security record this profile belongs to (kept, internal FK).
     */
    public function portalSecurity(): BelongsTo
    {
        return $this->belongsTo(Portalsecurity::class, 'eqa_portal_security_id', 'eqa_portalsecurity_id');
    }
}