<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

final class InstitutionPercentage extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'institution_percentage';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'institution_percentage_id';

    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Indicates if the model should be timestamped.
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
        'institution_percentage_id',
        'value',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'institution_percentage_id' => 'integer',
            'value' => 'string',
        ];
    }
}