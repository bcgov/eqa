<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Eloquent model for the eqa_applicationconfigurationBase table
 * (Dynamics CRM entity: eqa_applicationconfiguration).
 *
 * Source: EQADEV_MSCRM/eqa_applicationconfigurationBase
 */
class Applicationconfiguration extends Model
{
    /**
     * The database table associated with the model.
     */
    protected $table = 'eqa_applicationconfigurationBase';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'eqa_applicationconfigurationId';

    /**
     * The "type" of the primary key ID.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * Indicates if the model should be timestamped using Laravel's
     * default created_at/updated_at columns. Dynamics uses its own
     * CreatedOn/ModifiedOn columns instead.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'eqa_applicationconfigurationId',
        'CreatedOn',
        'CreatedBy',
        'ModifiedOn',
        'ModifiedBy',
        'CreatedOnBehalfBy',
        'ModifiedOnBehalfBy',
        'OrganizationId',
        'statecode',
        'statuscode',
        'ImportSequenceNumber',
        'OverriddenCreatedOn',
        'TimeZoneRuleVersionNumber',
        'UTCConversionTimeZoneCode',
        'eqa_name',
        'processid',
        'stageid',
        'traversedpath',
        'eqa_AnniversaryDate',
        'eqa_AnnualDesignationFee',
        'TransactionCurrencyId',
        'ExchangeRate',
        'eqa_annualdesignationfee_Base',
        'eqa_ApplicationFee',
        'eqa_applicationfee_Base',
        'eqa_ApplicationsYearEnd',
        'eqa_FeeProration',
        'eqa_NumericValue',
        'eqa_DesignationAnniversaryDate',
        'eqa_DraftExpiryDays',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'eqa_applicationconfigurationId' => 'string',
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
        'processid' => 'string',
        'stageid' => 'string',
        'traversedpath' => 'string',
        'eqa_AnniversaryDate' => 'datetime',
        'eqa_AnnualDesignationFee' => 'decimal:4',
        'TransactionCurrencyId' => 'string',
        'ExchangeRate' => 'decimal:10',
        'eqa_annualdesignationfee_Base' => 'decimal:4',
        'eqa_ApplicationFee' => 'decimal:4',
        'eqa_applicationfee_Base' => 'decimal:4',
        'eqa_ApplicationsYearEnd' => 'datetime',
        'eqa_FeeProration' => 'boolean',
        'eqa_NumericValue' => 'integer',
        'eqa_DesignationAnniversaryDate' => 'datetime',
        'eqa_DraftExpiryDays' => 'integer',
    ];

    /**
     * Scope a query to only include active records (statecode = 0).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('statecode', 0);
    }

    /**
     * Scope a query to only include inactive records (statecode = 1).
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('statecode', 1);
    }

    /**
     * Determine whether the configuration record is active.
     */
    protected function isActive(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => (int) $this->attributes['statecode'] === 0,
        );
    }
}