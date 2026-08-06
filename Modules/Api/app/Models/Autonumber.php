<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Autonumber extends Model
{
    use HasUuids;

    protected $table = 'eqa_autonumberbase';

    protected $primaryKey = 'eqa_autonumberid';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'createdby',
        'modifiedby',
        'createdonbehalfby',
        'modifiedonbehalfby',
        'ownerid',
        'owneridtype',
        'owningbusinessunit',
        'statecode',
        'statuscode',
        'versionnumber',
        'importsequencenumber',
        'overriddencreatedon',
        'timezoneruleversionnumber',
        'utcconversiontimezonecode',
        'eqa_name',
        'eqa_accountincrement',
        'eqa_accountnumber',
        'eqa_accountprefix',
        'eqa_accountsuffix',
        'eqa_applicationincrement',
        'eqa_applicationnumber',
        'eqa_applicationprefix',
        'eqa_applicationsuffix',
        'eqa_invoiceincrement',
        'eqa_invoicenumber',
        'eqa_invoiceprefix',
        'eqa_invoicesuffix',
    ];

    protected function casts(): array
    {
        return [
            'eqa_autonumberid' => 'string',
            'createdon' => 'datetime',
            'createdby' => 'string',
            'modifiedon' => 'datetime',
            'modifiedby' => 'string',
            'createdonbehalfby' => 'string',
            'modifiedonbehalfby' => 'string',
            'ownerid' => 'string',
            'owneridtype' => 'integer',
            'owningbusinessunit' => 'string',
            'statecode' => 'integer',
            'statuscode' => 'integer',
            'versionnumber' => 'integer',
            'importsequencenumber' => 'integer',
            'overriddencreatedon' => 'datetime',
            'timezoneruleversionnumber' => 'integer',
            'utcconversiontimezonecode' => 'integer',
            'eqa_name' => 'string',
            'eqa_accountincrement' => 'integer',
            'eqa_accountnumber' => 'integer',
            'eqa_accountprefix' => 'string',
            'eqa_accountsuffix' => 'string',
            'eqa_applicationincrement' => 'integer',
            'eqa_applicationnumber' => 'integer',
            'eqa_applicationprefix' => 'string',
            'eqa_applicationsuffix' => 'string',
            'eqa_invoiceincrement' => 'integer',
            'eqa_invoicenumber' => 'integer',
            'eqa_invoiceprefix' => 'string',
            'eqa_invoicesuffix' => 'string',
        ];
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Modules\Api\Models\SystemUser::class, 'ownerid', 'systemuserid');
    }

    public function createdBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Modules\Api\Models\SystemUser::class, 'createdby', 'systemuserid');
    }

    public function modifiedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Modules\Api\Models\SystemUser::class, 'modifiedby', 'systemuserid');
    }

    public function owningBusinessUnit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Modules\Api\Models\BusinessUnit::class, 'owningbusinessunit', 'businessunitid');
    }
}