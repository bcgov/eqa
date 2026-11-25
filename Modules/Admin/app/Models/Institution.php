<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A designated (or applicant) institution, migrated from the legacy Dynamics
 * Account entity. Read-only projection used by the admin + web portals.
 */
class Institution extends Model
{
    protected $table = 'institutions';

    protected $primaryKey = 'crm_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'designation_start' => 'date',
        'designation_expiry' => 'date',
        'ptib_cert_expiry' => 'date',
        'total_enrolment' => 'integer',
        'intl_students_permit' => 'integer',
        'intl_students_other' => 'integer',
        'in_person_students' => 'integer',
        'online_students' => 'integer',
    ];

    public function previousOperatingNames(): HasMany
    {
        return $this->hasMany(PreviousOperatingName::class, 'institution_crm_id', 'crm_id')->orderByDesc('used_from');
    }
}
