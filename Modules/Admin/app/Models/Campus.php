<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A campus / location of an institution, migrated from the legacy Dynamics
 * eqa_eqalocation entity. Read-only projection used by the portals.
 */
class Campus extends Model
{
    protected $table = 'campuses';

    protected $primaryKey = 'crm_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'primary_location' => 'boolean',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_crm_id', 'crm_id');
    }
}
