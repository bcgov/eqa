<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

class ResultType extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'result_type';

    protected $primaryKey = 'result_type_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'result_type_id',
        'event_type_code',
        'result_type_code',
        'result_type_value',
    ];

    protected $casts = [
        'result_type_id' => 'integer',
        'event_type_code' => 'integer',
        'result_type_code' => 'integer',
        'result_type_value' => 'string',
    ];
}