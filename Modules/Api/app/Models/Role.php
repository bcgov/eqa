<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'role';

    protected $primaryKey = 'role_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'role_id',
        'role_name',
        'description',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'role_id' => 'integer',
            'role_name' => 'string',
            'description' => 'string',
            'created_at' => 'date',
            'created_by' => 'string',
            'updated_at' => 'date',
            'updated_by' => 'string',
        ];
    }
}