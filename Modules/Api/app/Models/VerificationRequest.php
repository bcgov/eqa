<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationRequest extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'verification_request';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'verification_request_id';

    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Indicates if the IDs are auto-incrementing.
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
        'verification_request_id',
        'agency_id',
        'institution_id',
        'created_at',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verification_request_id' => 'integer',
            'agency_id' => 'integer',
            'institution_id' => 'integer',
            'created_at' => 'date',
            'created_by' => 'string',
        ];
    }
}