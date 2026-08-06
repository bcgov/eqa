<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CampusNotificationEmail
 *
 * Migrated from legacy CGI.Plugins model:
 * Plugins/CGI.Plugins/Notification Email/CampusNotificationEmail.cs
 */
class CampusNotificationEmail extends Model
{
    use HasFactory;

    protected $table = 'campus_notification_emails';

    protected $fillable = [
        'recipient_email',
        'subject',
        'body',
        'is_sent',
        'sent_at',
    ];

    protected $casts = [
        'recipient_email' => 'string',
        'subject' => 'string',
        'body' => 'string',
        'is_sent' => 'boolean',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected function recipientEmail(): Attribute
    {
        return Attribute::make(
            get: static fn (?string $value): ?string => $value !== null ? mb_strtolower($value) : null,
            set: static fn (?string $value): ?string => $value !== null ? mb_strtolower(trim($value)) : null,
        );
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'campus_id');
    }
}