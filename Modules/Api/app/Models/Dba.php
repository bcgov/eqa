<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $eqa_dba_id
 * @property \Illuminate\Support\Carbon|null $created_on
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $modified_on
 * @property string|null $modified_by
 * @property string|null $created_on_behalf_by
 * @property string|null $modified_on_behalf_by
 * @property string $owner_id
 * @property int $owner_id_type
 * @property string|null $owning_business_unit
 * @property int $statecode
 * @property int|null $statuscode
 * @property string|null $version_number
 * @property int|null $import_sequence_number
 * @property \Illuminate\Support\Carbon|null $overridden_created_on
 * @property int|null $time_zone_rule_version_number
 * @property int|null $utc_conversion_time_zone_code
 * @property string|null $eqa_name
 * @property string|null $email_address
 * @property string|null $eqa_city
 * @property int|null $eqa_country
 * @property string|null $eqa_description
 * @property string|null $eqa_email
 * @property string|null $eqa_institution_name
 * @property string|null $eqa_postal_code
 * @property int|null $eqa_province
 * @property string|null $eqa_street1
 * @property string|null $eqa_street2
 * @property string|null $eqa_street3
 * @property string|null $eqa_website_url
 */
final class Dba extends Model
{
    /**
     * The connection name for the model.
     */
    protected $connection = 'pgsql';

    /**
     * The table associated with the model.
     */
    protected $table = 'dbas';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'eqa_dba_id';

    /**
     * The data type of the primary key.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'eqa_dba_id',
        'created_on',
        'created_by',
        'modified_on',
        'modified_by',
        'created_on_behalf_by',
        'modified_on_behalf_by',
        'owner_id',
        'owner_id_type',
        'owning_business_unit',
        'statecode',
        'statuscode',
        'version_number',
        'import_sequence_number',
        'overridden_created_on',
        'time_zone_rule_version_number',
        'utc_conversion_time_zone_code',
        'eqa_name',
        'email_address',
        'eqa_city',
        'eqa_country',
        'eqa_description',
        'eqa_email',
        'eqa_institution_name',
        'eqa_postal_code',
        'eqa_province',
        'eqa_street1',
        'eqa_street2',
        'eqa_street3',
        'eqa_website_url',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'eqa_dba_id' => 'string',
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
            'version_number' => 'string',
            'import_sequence_number' => 'integer',
            'overridden_created_on' => 'datetime',
            'time_zone_rule_version_number' => 'integer',
            'utc_conversion_time_zone_code' => 'integer',
            'eqa_name' => 'string',
            'email_address' => 'string',
            'eqa_city' => 'string',
            'eqa_country' => 'integer',
            'eqa_description' => 'string',
            'eqa_email' => 'string',
            'eqa_institution_name' => 'string',
            'eqa_postal_code' => 'string',
            'eqa_province' => 'integer',
            'eqa_street1' => 'string',
            'eqa_street2' => 'string',
            'eqa_street3' => 'string',
            'eqa_website_url' => 'string',
        ];
    }

    /**
     * The institution (external Dynamics AccountBase.AccountId) this DBA belongs to.
     *
     * Note: AccountBase is not migrated into this application (ref_kept = false,
     * ref_external = true), so this relationship is intentionally not defined as
     * an Eloquent relation. The raw identifier is exposed via the
     * eqa_institution_name attribute for reference/reporting purposes only.
     */
    protected function institutionRelationNotAvailable(): void
    {
        // Intentionally left unimplemented: target entity is external and not modeled locally.
    }

    /**
     * Scope a query to only include active records (statecode = 0).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('statecode', 0);
    }
}