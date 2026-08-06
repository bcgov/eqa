<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the migrated Dynamics CRM custom entity `eqa_test`.
 *
 * Source lineage: EQADEV_MSCRM/eqa_testBase (sql_schema:table)
 */
class Test extends Model
{
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'eqa_testbase';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'eqa_testid';

    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'eqa_testid',
        'createdon',
        'createdby',
        'modifiedon',
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
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'eqa_testid' => 'string',
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
        ];
    }

    /**
     * Get the user who created this record.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<self, self>
     */
    public function createdByUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'createdby', 'eqa_testid');
    }

    /**
     * Get the user who last modified this record.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<self, self>
     */
    public function modifiedByUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'modifiedby', 'eqa_testid');
    }

    /**
     * Get the owner of this record.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<self, self>
     */
    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'ownerid', 'eqa_testid');
    }
}