<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $contact_id
 * @property int $role_id
 */
class ContactRole extends Model
{
    /**
     * Source lineage: EQADM/contact_role (sql_schema:table)
     */
    protected $table = 'contact_role';

    public $incrementing = false;

    protected $primaryKey = null;

    public $timestamps = false;

    protected $fillable = [
        'contact_id',
        'role_id',
    ];

    protected $casts = [
        'contact_id' => 'integer',
        'role_id' => 'integer',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}