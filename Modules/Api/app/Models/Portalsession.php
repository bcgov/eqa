<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property string $eqa_portalsessionid
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
 * @property \Illuminate\Support\Carbon|null $eqa_end_date
 * @property string|null $eqa_portal_profile_id
 * @property \Illuminate\Support\Carbon|null $eqa_start_date
 */
class Portalsession extends Model
{
    protected $table = 'eqa_portalsessionbase';

    protected $primaryKey = 'eqa_portalsessionid';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

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
        'version_number',
        'import_sequence_number',
        'overridden_created_on',
        'time_zone_rule_version_number',
        'utc_conversion_time_zone_code',
        'eqa_name',
        'eqa_end_date',
        'eqa_portal_profile_id',
        'eqa_start_date',
    ];

    protected function casts(): array
    {
        return [
            'eqa_portalsessionid' => 'string',
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
            'import_sequence_number' => 'integer',
            'overridden_created_on' => 'datetime',
            'time_zone_rule_version_number' => 'integer',
            'utc_conversion_time_zone_code' => 'integer',
            'eqa_name' => 'string',
            'eqa_end_date' => 'datetime',
            'eqa_portal_profile_id' => 'string',
            'eqa_start_date' => 'datetime',
        ];
    }

    public function portalProfile(): BelongsTo
    {
        return $this->belongsTo(Portalprofile::class, 'eqa_portal_profile_id', 'eqa_portalprofileid');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('statecode', 0);
    }
}