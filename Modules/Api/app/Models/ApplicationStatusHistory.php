<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationStatusHistory extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'application_status_history';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'appl_status_hist_id';

    /**
     * Indicates if the model should be timestamped.
     *
     * Source columns created_at/created_by are legacy varchar audit fields,
     * not native Eloquent timestamps, and there is no updated_at column.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'application_id',
        'status_id',
        'comments',
        'active',
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
            'appl_status_hist_id' => 'integer',
            'application_id' => 'integer',
            'status_id' => 'integer',
            'comments' => 'string',
            'active' => 'boolean',
            'created_at' => 'string',
            'created_by' => 'string',
        ];
    }

    /**
     * The application this status history entry belongs to.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    /**
     * The status recorded at this point in the application's history.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_id');
    }
}