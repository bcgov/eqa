<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Address extends Model
{
    protected $table = 'address';

    protected $primaryKey = 'address_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'institution_id',
        'campus_name',
        'address_line_1',
        'address_line_2',
        'address_line_3',
        'city',
        'province_id',
        'postal_code',
        'country_id',
        'primary_address',
        'web_address',
        'active',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
    ];

    protected $casts = [
        'address_id' => 'integer',
        'institution_id' => 'integer',
        'campus_name' => 'string',
        'address_line_1' => 'string',
        'address_line_2' => 'string',
        'address_line_3' => 'string',
        'city' => 'string',
        'province_id' => 'string',
        'postal_code' => 'string',
        'country_id' => 'string',
        'primary_address' => 'integer',
        'web_address' => 'string',
        'active' => 'integer',
        'created_at' => 'date',
        'created_by' => 'string',
        'updated_at' => 'date',
        'updated_by' => 'string',
    ];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_id');
    }
}