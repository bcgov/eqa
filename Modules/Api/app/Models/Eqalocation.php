<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $eqa_eqalocationId
 * @property string|null $eqa_name
 */
class Eqalocation extends Model
{
    use HasFactory;

    protected $table = 'eqa_eqalocationBase';

    protected $primaryKey = 'eqa_eqalocationId';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'CreatedOn',
        'CreatedBy',
        'ModifiedOn',
        'ModifiedBy',
        'CreatedOnBehalfBy',
        'ModifiedOnBehalfBy',
        'OwnerId',
        'OwnerIdType',
        'OwningBusinessUnit',
        'statecode',
        'statuscode',
        'VersionNumber',
        'ImportSequenceNumber',
        'OverriddenCreatedOn',
        'TimeZoneRuleVersionNumber',
        'UTCConversionTimeZoneCode',
        'eqa_name',
        'processid',
        'stageid',
        'traversedpath',
        'eqa_City',
        'eqa_Country',
        'eqa_DBANamenew',
        'eqa_Description',
        'eqa_Email',
        'eqa_InstitutionName',
        'eqa_LegacyID',
        'eqa_LocationName',
        'eqa_ParentType',
        'eqa_PostalCode',
        'eqa_PrimaryLocation',
        'eqa_Province',
        'eqa_Street1',
        'eqa_Street2',
        'eqa_Street3',
        'eqa_WebsiteURL',
    ];

    protected function casts(): array
    {
        return [
            'eqa_eqalocationId' => 'string',
            'CreatedOn' => 'datetime',
            'CreatedBy' => 'string',
            'ModifiedOn' => 'datetime',
            'ModifiedBy' => 'string',
            'CreatedOnBehalfBy' => 'string',
            'ModifiedOnBehalfBy' => 'string',
            'OwnerId' => 'string',
            'OwnerIdType' => 'integer',
            'OwningBusinessUnit' => 'string',
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
            'eqa_City' => 'string',
            'eqa_Country' => 'integer',
            'eqa_DBANamenew' => 'string',
            'eqa_Description' => 'string',
            'eqa_Email' => 'string',
            'eqa_InstitutionName' => 'string',
            'eqa_LegacyID' => 'string',
            'eqa_LocationName' => 'string',
            'eqa_ParentType' => 'integer',
            'eqa_PostalCode' => 'string',
            'eqa_PrimaryLocation' => 'boolean',
            'eqa_Province' => 'integer',
            'eqa_Street1' => 'string',
            'eqa_Street2' => 'string',
            'eqa_Street3' => 'string',
            'eqa_WebsiteURL' => 'string',
        ];
    }

    public function dba(): BelongsTo
    {
        return $this->belongsTo(Dba::class, 'eqa_DBANamenew', 'eqa_dbaId');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'eqa_InstitutionName', 'AccountId');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(SystemUser::class, 'CreatedBy', 'SystemUserId');
    }

    public function modifiedBy(): BelongsTo
    {
        return $this->belongsTo(SystemUser::class, 'ModifiedBy', 'SystemUserId');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(SystemUser::class, 'OwnerId', 'SystemUserId');
    }
}