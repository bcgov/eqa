<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

class Fee extends Model
{
    protected $table = 'fee';

    public $incrementing = false;

    protected $primaryKey = 'fee_id';

    public $timestamps = false;

    protected $fillable = [
        'fee_id',
        'fee_description',
        'fee_amount',
        'fee_effective_date',
        'fee_expiry_date',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
    ];

    protected $casts = [
        'fee_id' => 'integer',
        'fee_description' => 'string',
        'fee_amount' => 'decimal:2',
        'fee_effective_date' => 'date',
        'fee_expiry_date' => 'date',
        'created_at' => 'date',
        'created_by' => 'string',
        'updated_at' => 'date',
        'updated_by' => 'string',
    ];
}