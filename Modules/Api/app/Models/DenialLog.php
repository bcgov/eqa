<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Source lineage: EQADM/eqa_denial_log (sql_schema:table)
 */
class DenialLog extends Model
{
    protected $table = 'eqa_denial_log';

    protected $primaryKey = 'denial_log_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'institution_id',
        'application_id',
        'reason_code',
        'created_at',
        'created_by',
    ];

    protected $casts = [
        'denial_log_id' => 'integer',
        'institution_id' => 'integer',
        'application_id' => 'integer',
        'reason_code' => 'integer',
        'created_at' => 'date',
        'created_by' => 'string',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }
}