<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Api\Database\Factories\StatusHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $eqa_status_history_id
 * @property int|null $institution_id
 * @property int|null $application_id
 * @property int|null $status_id
 * @property int|null $reason_code
 * @property string|null $effective_date
 * @property string|null $comments
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property string|null $created_by
 */
class StatusHistory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'eqa_status_history';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'eqa_status_history_id';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'institution_id',
        'application_id',
        'status_id',
        'reason_code',
        'effective_date',
        'comments',
        'created_at',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'eqa_status_history_id' => 'integer',
            'institution_id' => 'integer',
            'application_id' => 'integer',
            'status_id' => 'integer',
            'reason_code' => 'integer',
            'effective_date' => 'string',
            'comments' => 'string',
            'created_at' => 'date',
            'created_by' => 'string',
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): StatusHistoryFactory
    {
        return StatusHistoryFactory::new();
    }

    /**
     * Get the application that owns the status history record.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    /**
     * Get the status associated with the status history record.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_id');
    }
}