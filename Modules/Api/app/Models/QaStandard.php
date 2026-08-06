<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

class QaStandard extends Model
{
    protected $table = 'qa_standards';

    protected $primaryKey = 'standard_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'standard_id',
        'standard_value',
        'ministry_verification',
        'pctia_verification',
        'lc_verification',
        'hidden',
        'sort_order',
        'fee_amount',
    ];

    protected $casts = [
        'standard_id' => 'integer',
        'standard_value' => 'string',
        'ministry_verification' => 'integer',
        'pctia_verification' => 'integer',
        'lc_verification' => 'integer',
        'hidden' => 'boolean',
        'sort_order' => 'integer',
        'fee_amount' => 'decimal:2',
    ];
}