<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Source lineage: EQADM/institution_application_history (sql_schema:table)
 *
 * @property int $institution_application_history_id
 * @property int $result_type_id
 * @property string $event_datetime
 * @property string|null $result_type_details
 * @property string|null $comments
 * @property int|null $institution_id
 * @property int|null $application_id
 * @property string|null $created_at
 * @property string|null $created_by
 * @property string|null $updated_at
 * @property string|null $updated_by
 */
class InstitutionApplicationHistory extends Model
{
    protected $table = 'institution_application_history';

    protected $primaryKey = 'institution_application_history_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'result_type_id',
        'event_datetime',
        'result_type_details',
        'comments',
        'institution_id',
        'application_id',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
    ];

    protected $casts = [
        'institution_application_history_id' => 'integer',
        'result_type_id' => 'integer',
        'event_datetime' => 'string',
        'result_type_details' => 'string',
        'comments' => 'string',
        'institution_id' => 'integer',
        'application_id' => 'integer',
        'created_at' => 'string',
        'created_by' => 'string',
        'updated_at' => 'string',
        'updated_by' => 'string',
    ];

    public function resultType(): BelongsTo
    {
        return $this->belongsTo(ResultType::class, 'result_type_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_id');
    }
}