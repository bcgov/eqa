<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A DBA ("Doing Business As" name) of an institution, migrated from the legacy
 * Dynamics eqa_dba entity. Read-only projection used by the portals.
 */
class Dba extends Model
{
    protected $table = 'dbas';

    protected $primaryKey = 'crm_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_crm_id', 'crm_id');
    }
}
