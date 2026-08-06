<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $table = 'task';

    protected $primaryKey = 'task_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'task_id',
        'task_description',
        'task_status',
        'task_notes',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'task_id' => 'integer',
            'task_description' => 'string',
            'task_status' => 'string',
            'task_notes' => 'string',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    protected function taskNotes(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $this->attributes['task_notes'] ?? $value,
        );
    }
}