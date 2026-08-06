<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Represents a row in the legacy EQADM.dbo.agency_standing_change_log table,
 * recording historical changes to an agency's standing/status.
 */
class AgencyStandingChangeLog extends Model
{
    /**
     * The database table associated with the model.
     *
     * @var string
     */
    protected $table = 'agency_standing_change_log';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'log_id';

    /**
     * The primary key is not an auto-incrementing surrogate in the source system.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * This table only tracks a single `created_at` timestamp column and has
     * no `updated_at` column, so Eloquent's default timestamp management is
     * disabled in favor of an explicit cast below.
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
        'log_id',
        'agency_id',
        'institution_id',
        'pctia_no',
        'old_status',
        'new_status',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'log_id' => 'integer',
            'agency_id' => 'integer',
            'institution_id' => 'integer',
            'pctia_no' => 'integer',
            'old_status' => 'integer',
            'new_status' => 'integer',
            'created_at' => 'date',
        ];
    }
}