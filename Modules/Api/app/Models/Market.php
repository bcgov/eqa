<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

final class Market extends Model
{
    protected $connection = 'sqlsrv_eqadm';

    protected $table = 'market';

    protected $primaryKey = 'market_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'market_id',
        'name',
        'is_country',
    ];

    protected $casts = [
        'market_id' => 'integer',
        'name' => 'string',
        'is_country' => 'boolean',
    ];
}