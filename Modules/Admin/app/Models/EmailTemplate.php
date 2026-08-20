<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A ministry-editable email template migrated from the legacy Dynamics
 * notification-email plugins. Body/subject support {{placeholder}} tokens
 * rendered by EmailNotificationService.
 */
class EmailTemplate extends Model
{
    protected $table = 'email_templates';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
