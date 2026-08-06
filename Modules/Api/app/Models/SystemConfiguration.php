<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $value
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $effective_date
 * @property \Illuminate\Support\Carbon|null $expiry_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $updated_by
 * @property bool $renewal_enabled
 */
class SystemConfiguration extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'system_configuration';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model should be timestamped.
     *
     * The source table only tracks date-precision created_at/updated_at
     * columns (no created_at/updated_at datetime pair managed by
     * Eloquent's automatic timestamps), so automatic timestamp
     * management is disabled and the columns are cast manually below.
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
        'code',
        'value',
        'description',
        'effective_date',
        'expiry_date',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'renewal_enabled',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'code' => 'string',
            'value' => 'string',
            'description' => 'string',
            'effective_date' => 'date',
            'expiry_date' => 'date',
            'created_at' => 'date',
            'created_by' => 'string',
            'updated_at' => 'date',
            'updated_by' => 'string',
            'renewal_enabled' => 'boolean',
        ];
    }
}