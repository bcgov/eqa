<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent model for the legacy Dynamics CRM `eqa_applicationprocessBase` table
 * (Business Process Flow instance: Application Process).
 *
 * Source lineage: EQADEV_MSCRM/eqa_applicationprocessBase (sql_schema:table)
 */
class Applicationprocess extends Model
{
    /**
     * The database table used by the model.
     */
    protected $table = 'eqa_applicationprocessBase';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'BusinessProcessFlowInstanceId';

    /**
     * Indicates whether the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID.
     */
    protected $keyType = 'string';

    /**
     * Timestamps are managed manually via CreatedOn/ModifiedOn columns.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'BusinessProcessFlowInstanceId',
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
        'bpf_name',
        'ActiveStageId',
        'ProcessId',
        'TraversedPath',
        'CompletedOn',
        'ActiveStageStartedOn',
        'bpf_eqa_eqaapplicationid',
    ];

    /**
     * The attributes that should be cast.
     *
     * `bpf_Duration` is a computed column and is excluded from fillable but
     * still cast for read access. `VersionNumber` is a SQL Server rowversion
     * (timestamp) column and is not writable.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'BusinessProcessFlowInstanceId' => 'string',
        'CreatedOn' => 'datetime',
        'CreatedBy' => 'string',
        'ModifiedOn' => 'datetime',
        'ModifiedBy' => 'string',
        'CreatedOnBehalfBy' => 'string',
        'ModifiedOnBehalfBy' => 'string',
        'OrganizationId' => 'string',
        'statecode' => 'integer',
        'statuscode' => 'integer',
        'VersionNumber' => 'string',
        'ImportSequenceNumber' => 'integer',
        'OverriddenCreatedOn' => 'datetime',
        'TimeZoneRuleVersionNumber' => 'integer',
        'UTCConversionTimeZoneCode' => 'integer',
        'bpf_name' => 'string',
        'ActiveStageId' => 'string',
        'ProcessId' => 'string',
        'TraversedPath' => 'string',
        'CompletedOn' => 'datetime',
        'ActiveStageStartedOn' => 'datetime',
        'bpf_eqa_eqaapplicationid' => 'string',
        'bpf_Duration' => 'integer',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [];

    /**
     * The user who created this business process flow instance.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Modules\Api\Models\Systemuser, $this>
     */
    public function createdByUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Modules\Api\Models\Systemuser::class, 'CreatedBy', 'systemuserid');
    }

    /**
     * The user who last modified this business process flow instance.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Modules\Api\Models\Systemuser, $this>
     */
    public function modifiedByUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Modules\Api\Models\Systemuser::class, 'ModifiedBy', 'systemuserid');
    }

    /**
     * The related eqa_eqaapplication record this process instance is tracking.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Modules\Api\Models\Eqaapplication, $this>
     */
    public function application(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Modules\Api\Models\Eqaapplication::class, 'bpf_eqa_eqaapplicationid', 'eqa_eqaapplicationid');
    }

    /**
     * Scope a query to only include active records (statecode = 0).
     *
     * @param \Illuminate\Database\Eloquent\Builder<\Modules\Api\Models\Applicationprocess> $query
     * @return \Illuminate\Database\Eloquent\Builder<\Modules\Api\Models\Applicationprocess>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('statecode', 0);
    }
}