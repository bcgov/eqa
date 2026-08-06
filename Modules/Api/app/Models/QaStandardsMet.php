<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model for the legacy EQADM.dbo.qa_standards_met table.
 *
 * This is a pure junction table (no surrogate primary key, no timestamps)
 * linking applications to the QA standards they have met.
 */
final class QaStandardsMet extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'qa_standards_met';

    /**
     * The table does not have a single-column auto-incrementing primary key.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * No surrogate primary key exists on this junction table.
     *
     * @var string|null
     */
    protected $primaryKey = null;

    /**
     * The source table has no created_at / updated_at columns.
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
        'application_id',
        'standard_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'application_id' => 'integer',
            'standard_id' => 'integer',
        ];
    }

    /**
     * The application that met this QA standard.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    /**
     * The QA standard that was met by the application.
     */
    public function standard(): BelongsTo
    {
        return $this->belongsTo(Standard::class, 'standard_id');
    }
}