<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A record of every notification email the system dispatched (or attempted).
 * Written by EmailNotificationService and surfaced per-institution on the admin
 * InstitutionView page.
 */
class SentEmail extends Model
{
    protected $table = 'sent_emails';

    protected $guarded = [];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}
