<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Source lineage: EQADM.dbo.education_institution (sql_schema, ref: EQADM/education_institution)
 */
class EducationInstitution extends Model
{
    protected $table = 'education_institution';

    protected $primaryKey = 'institution_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'institution_id',
        'institution_type',
        'institution_name',
        'eqa_status_code',
        'institution_size_id',
        'institution_percentage_id',
        'owner_name',
        'legal_name',
        'doing_business_as',
        'pictia_id',
        'lc_id',
        'student_percentage_type',
        'institution_size_type',
        'institution_description',
        'institution_signature',
        'pctia_standing',
        'ministry_standing',
        'lc_standing',
        'incorporation_number',
        'start_date',
        'expiry_date',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'institution_id' => 'integer',
            'institution_type' => 'integer',
            'institution_name' => 'string',
            'eqa_status_code' => 'integer',
            'institution_size_id' => 'integer',
            'institution_percentage_id' => 'integer',
            'owner_name' => 'string',
            'legal_name' => 'string',
            'doing_business_as' => 'string',
            'pictia_id' => 'integer',
            'lc_id' => 'string',
            'student_percentage_type' => 'string',
            'institution_size_type' => 'string',
            'institution_description' => 'string',
            'institution_signature' => 'string',
            'pctia_standing' => 'integer',
            'ministry_standing' => 'integer',
            'lc_standing' => 'integer',
            'incorporation_number' => 'string',
            'start_date' => 'datetime',
            'expiry_date' => 'datetime',
            'created_at' => 'datetime',
            'created_by' => 'string',
            'updated_at' => 'datetime',
            'updated_by' => 'string',
        ];
    }

    /**
     * Inferred relationship from institution_size_id.
     */
    public function institutionSize(): BelongsTo
    {
        return $this->belongsTo(InstitutionSize::class, 'institution_size_id');
    }

    /**
     * Inferred relationship from institution_percentage_id.
     */
    public function institutionPercentage(): BelongsTo
    {
        return $this->belongsTo(InstitutionPercentage::class, 'institution_percentage_id');
    }
}