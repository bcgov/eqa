<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A campus location entered on an application. Independent of the institution's campuses
 * (no prefill, no sync) and without a status.
 */
class ApplicationCampusLocation extends Model
{
    protected $table = 'application_campus_locations';

    protected $fillable = [
        'application_crm_id', 'location_name', 'email', 'website', 'street1', 'street2',
        'city', 'province', 'postal_code', 'country', 'primary_location', 'sort_order',
    ];

    protected $casts = [
        'primary_location' => 'boolean',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_crm_id', 'crm_id');
    }
}
