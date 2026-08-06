<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StandingRequestTaskResult extends Model
{
    protected $table = 'standing_request_task_result';

    protected $primaryKey = 'standing_request_task_result_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'standing_request_task_id',
        'institution_id',
        'institution_standing_request_status',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'standing_request_task_id' => 'integer',
            'institution_id' => 'integer',
            'institution_standing_request_status' => 'string',
            'created_at' => 'date',
        ];
    }

    public function standingRequestTask(): BelongsTo
    {
        return $this->belongsTo(StandingRequestTask::class, 'standing_request_task_id');
    }
}