<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model representing a Student Data XML Scribe export record.
 *
 * Migrated from EQA_API.Models.v1.StudentDataModels (StudentDataXMLScribeExport),
 * originally sourced from `EQA API/Models/v1/StudentDataModels.cs`.
 *
 * The legacy C# model exposed 63 flat properties describing a student export
 * payload (identifiers, demographics, enrollment, address, and audit fields).
 * The full property list was not available in the intermediate representation
 * supplied for this migration, so the schema below models the well-known
 * export fields explicitly and stores any additional/unclassified legacy
 * fields inside a JSON `extra_attributes` column to avoid silent data loss.
 */
class StudentDataXMLScribeExport extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'student_data_xml_scribe_exports';

    /**
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * @var bool
     */
    public $incrementing = true;

    /**
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Export snapshots are immutable point-in-time records; Scribe writes
     * `exported_at` explicitly rather than relying on Eloquent timestamps.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'student_id',
        'state_student_id',
        'local_student_id',
        'district_id',
        'school_id',
        'school_year',
        'first_name',
        'middle_name',
        'last_name',
        'name_suffix',
        'birth_date',
        'gender',
        'ethnicity',
        'race',
        'grade_level',
        'enrollment_status',
        'enrollment_date',
        'exit_date',
        'exit_reason',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'county',
        'phone_number',
        'email_address',
        'guardian_name',
        'guardian_phone',
        'guardian_email',
        'is_special_education',
        'is_english_language_learner',
        'is_economically_disadvantaged',
        'is_migrant',
        'is_homeless',
        'is_active',
        'scribe_batch_id',
        'scribe_status',
        'scribe_error_message',
        'source_system',
        'exported_at',
        'extra_attributes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'enrollment_date' => 'date',
            'exit_date' => 'date',
            'is_special_education' => 'boolean',
            'is_english_language_learner' => 'boolean',
            'is_economically_disadvantaged' => 'boolean',
            'is_migrant' => 'boolean',
            'is_homeless' => 'boolean',
            'is_active' => 'boolean',
            'exported_at' => 'datetime',
            'extra_attributes' => 'array',
        ];
    }

    /**
     * Full name accessor combining the legacy first/middle/last/suffix fields.
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim(
                sprintf(
                    '%s %s %s %s',
                    (string) $this->first_name,
                    (string) $this->middle_name,
                    (string) $this->last_name,
                    (string) $this->name_suffix,
                )
            ),
        );
    }
}