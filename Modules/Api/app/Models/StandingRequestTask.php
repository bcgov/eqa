<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Source lineage: EQADM/standing_request_task (sql_schema:table)
 */
class StandingRequestTask extends Model
{
    protected $table = 'standing_request_task';

    protected $primaryKey = 'standing_request_task_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'task_id',
        'connection_url',
    ];

    protected $casts = [
        'standing_request_task_id' => 'integer',
        'task_id' => 'integer',
        'connection_url' => 'string',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }
}