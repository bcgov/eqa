<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An operating name an institution used in the last 5 years, as declared on an application.
 * Belongs to the institution and (when declared on one) to the application.
 */
class PreviousOperatingName extends Model
{
    protected $table = 'previous_operating_names';

    protected $fillable = ['institution_crm_id', 'application_crm_id', 'name', 'used_from', 'used_to'];

    protected $casts = [
        'used_from' => 'date',
        'used_to' => 'date',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_crm_id', 'crm_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_crm_id', 'crm_id');
    }

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }
}
