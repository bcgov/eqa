<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * DbaNotificationEmail
 *
 * Migrated from legacy CGI.Plugins notification email DBA model.
 * Source: Plugins/CGI.Plugins/Notification Email/DbaNotificationEmail.cs
 *
 * @property int $id
 * @property string $recipient_email
 * @property string $subject
 * @property string $body
 * @property bool $is_sent
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class DbaNotificationEmail extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'dba_notification_emails';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'recipient_email',
        'subject',
        'body',
        'is_sent',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_sent' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}