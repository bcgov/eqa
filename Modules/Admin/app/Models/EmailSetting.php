<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row store for the global "email sending" master switch. Sending is
 * OFF by default until the ministry explicitly enables it.
 */
class EmailSetting extends Model
{
    protected $table = 'email_settings';

    protected $guarded = [];

    protected $casts = [
        'sending_enabled' => 'boolean',
    ];
}
