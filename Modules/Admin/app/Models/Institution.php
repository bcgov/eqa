<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

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
}
