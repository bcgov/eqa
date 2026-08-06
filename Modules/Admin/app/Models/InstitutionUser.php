<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An institution portal user / contact, migrated from the legacy Dynamics
 * Contact entity. Credentials (eqa_WebUserPassword) are never migrated.
 * Read-only projection used by the web portal's Manage Users page.
 */
class InstitutionUser extends Model
{
    protected $table = 'institution_users';

    protected $primaryKey = 'crm_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'web_user_active' => 'boolean',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_crm_id', 'crm_id');
    }
}
