<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Source lineage: EQADM/dedesignation_reason (sql_schema:table)
 */
class DedesignationReason extends Model
{
    protected $table = 'dedesignation_reason';

    protected $primaryKey = 'de_design_reason_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'de_design_reason_id',
        'reason_code',
        'reason_desc',
    ];

    protected $casts = [
        'de_design_reason_id' => 'integer',
        'reason_code' => 'integer',
        'reason_desc' => 'string',
    ];
}