<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model for PortalLogon.
 *
 * Migrated from EQA_API.Models.v1.PortalLogon (Portal.SecurityModels.cs).
 */
class PortalLogon extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'portal_logons';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'portal_id',
        'username',
        'password_hash',
        'email',
        'first_name',
        'last_name',
        'company_name',
        'phone_number',
        'is_active',
        'is_locked',
        'failed_login_attempts',
        'last_login_at',
        'last_failed_login_at',
        'password_changed_at',
        'password_expires_at',
        'must_change_password',
        'security_question',
        'security_answer_hash',
        'remember_token_hash',
        'session_token',
        'session_expires_at',
        'ip_address',
        'user_agent',
        'time_zone',
        'locale',
        'is_admin',
        'role',
        'created_by',
        'updated_by',
        'notes',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
        'security_answer_hash',
        'remember_token_hash',
        'session_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'portal_id' => 'integer',
            'is_active' => 'boolean',
            'is_locked' => 'boolean',
            'failed_login_attempts' => 'integer',
            'last_login_at' => 'datetime',
            'last_failed_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'password_expires_at' => 'datetime',
            'must_change_password' => 'boolean',
            'session_expires_at' => 'datetime',
            'is_admin' => 'boolean',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the portal that owns the logon.
     */
    public function portal(): BelongsTo
    {
        return $this->belongsTo(Portal::class, 'portal_id');
    }

    /**
     * Get the user account associated with the logon.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who created this logon record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this logon record.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Determine if the account is currently usable for authentication.
     */
    protected function isUsable(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => (bool) $this->is_active && ! (bool) $this->is_locked,
        );
    }
}