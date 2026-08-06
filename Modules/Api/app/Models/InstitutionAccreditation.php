<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Source lineage: EQADM/institution_accreditation (sql_schema:table)
 */
class InstitutionAccreditation extends Model
{
    protected $table = 'institution_accreditation';

    protected $primaryKey = 'institution_accreditation_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'institution_id',
        'accreditation_agency_id',
        'accreditation_status',
        'accreditation_start_date',
        'accreditation_expiry_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'institution_accreditation_id' => 'integer',
        'institution_id' => 'integer',
        'accreditation_agency_id' => 'integer',
        'accreditation_status' => 'string',
        'accreditation_start_date' => 'date',
        'accreditation_expiry_date' => 'date',
        'created_at' => 'date',
        'created_by' => 'string',
        'updated_at' => 'date',
        'updated_by' => 'string',
    ];

    public function accreditationAgency(): BelongsTo
    {
        return $this->belongsTo(AccreditationAgency::class, 'accreditation_agency_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_id');
    }
}