<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent model for the legacy Dynamics entity `eqa_configurationBase`
 * (source: EQADEV_MSCRM/eqa_configurationBase).
 */
class Configuration extends Model
{
    protected $table = 'configuration';

    protected $primaryKey = 'eqa_configuration_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'created_by',
        'modified_by',
        'created_on_behalf_by',
        'modified_on_behalf_by',
        'organization_id',
        'statecode',
        'statuscode',
        'import_sequence_number',
        'overridden_created_on',
        'time_zone_rule_version_number',
        'utc_conversion_time_zone_code',
        'eqa_name',
        'eqa_description',
        'eqa_value',
    ];

    protected $casts = [
        'eqa_configuration_id' => 'string',
        'created_on' => 'datetime',
        'created_by' => 'string',
        'modified_on' => 'datetime',
        'modified_by' => 'string',
        'created_on_behalf_by' => 'string',
        'modified_on_behalf_by' => 'string',
        'organization_id' => 'string',
        'statecode' => 'integer',
        'statuscode' => 'integer',
        'version_number' => 'integer',
        'import_sequence_number' => 'integer',
        'overridden_created_on' => 'datetime',
        'time_zone_rule_version_number' => 'integer',
        'utc_conversion_time_zone_code' => 'integer',
        'eqa_name' => 'string',
        'eqa_description' => 'string',
        'eqa_value' => 'string',
    ];

    /**
     * Scope a query to only include active configurations (statecode = 0).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('statecode', 0);
    }
}