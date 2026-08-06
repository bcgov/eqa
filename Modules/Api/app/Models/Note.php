<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

final class Note extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'note';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'note_id';

    /**
     * The data type of the primary key.
     */
    protected $keyType = 'int';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The source table stores created_at/updated_at as free-form varchar
     * strings rather than native timestamps, so Eloquent must not manage
     * them automatically.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'note_id',
        'app_id',
        'internal',
        'note',
        'active',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'note_id' => 'integer',
            'app_id' => 'integer',
            'internal' => 'integer',
            'note' => 'string',
            'active' => 'integer',
            'created_at' => 'string',
            'created_by' => 'string',
            'updated_at' => 'string',
            'updated_by' => 'string',
        ];
    }
}