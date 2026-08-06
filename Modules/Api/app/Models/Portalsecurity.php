<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the legacy Dynamics CRM entity `eqa_portalsecurity`.
 *
 * Source: EQADEV_MSCRM.dbo.eqa_portalsecurityBase
 */
class Portalsecurity extends Model
{
    use HasFactory;

    /**
     * The database connection that should be used by the model.
     */
    protected $connection = 'sqlsrv';

    /**
     * The table associated with the model.
     */
    protected $table = 'eqa_portalsecurityBase';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'eqa_portalsecurityId';

    /**
     * The "type" of the auto-incrementing ID.
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
     * @var list<string>
     */
    protected $fillable = [
        'CreatedOn',
        'CreatedBy',
        'ModifiedOn',
        'ModifiedBy',
        'CreatedOnBehalfBy',
        'ModifiedOnBehalfBy',
        'OrganizationId',
        'statecode',
        'statuscode',
        'VersionNumber',
        'ImportSequenceNumber',
        'OverriddenCreatedOn',
        'TimeZoneRuleVersionNumber',
        'UTCConversionTimeZoneCode',
        'eqa_name',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'eqa_portalsecurityId' => 'string',
            'CreatedOn' => 'datetime',
            'CreatedBy' => 'string',
            'ModifiedOn' => 'datetime',
            'ModifiedBy' => 'string',
            'CreatedOnBehalfBy' => 'string',
            'ModifiedOnBehalfBy' => 'string',
            'OrganizationId' => 'string',
            'statecode' => 'integer',
            'statuscode' => 'integer',
            'VersionNumber' => 'integer',
            'ImportSequenceNumber' => 'integer',
            'OverriddenCreatedOn' => 'datetime',
            'TimeZoneRuleVersionNumber' => 'integer',
            'UTCConversionTimeZoneCode' => 'integer',
            'eqa_name' => 'string',
        ];
    }

    /**
     * Scope a query to only include active records (statecode = 0).
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('statecode', 0);
    }

    /**
     * The display name attribute for this security record.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value ?? $this->attributes['eqa_name'] ?? null,
        );
    }
}