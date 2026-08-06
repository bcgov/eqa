<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

class Dedesignation extends Model
{
    protected $table = 'dedesignation';

    public $timestamps = false;

    protected $fillable = [
        'de_design_id',
        'institution_id',
        'de_design_reason_id',
        'effective_date',
        'status',
        'comment',
        'created_at',
        'created_by',
    ];

    protected $casts = [
        'de_design_id' => 'integer',
        'institution_id' => 'integer',
        'de_design_reason_id' => 'integer',
        'effective_date' => 'date',
        'status' => 'string',
        'comment' => 'string',
        'created_at' => 'date',
        'created_by' => 'string',
    ];
}