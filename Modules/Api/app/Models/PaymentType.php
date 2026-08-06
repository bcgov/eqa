<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentType extends Model
{
    protected $table = 'payment_type';

    public $incrementing = false;

    protected $primaryKey = 'payment_type_code';

    public $timestamps = false;

    protected $keyType = 'int';

    protected $fillable = [
        'payment_type_code',
        'payment_description',
    ];

    protected function casts(): array
    {
        return [
            'payment_type_code' => 'integer',
            'payment_description' => 'string',
        ];
    }
}