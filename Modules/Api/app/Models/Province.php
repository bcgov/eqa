<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Province extends Model
{
    protected $table = 'province';

    protected $primaryKey = 'iso_code';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'iso_code',
        'name',
        'country_id',
    ];

    protected $casts = [
        'iso_code' => 'string',
        'name' => 'string',
        'country_id' => 'string',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id', 'iso_code');
    }
}