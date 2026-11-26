<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An EQA designation application, migrated from the legacy Dynamics
 * eqa_eqaapplication entity. Read-only projection used by both portals.
 */
class Application extends Model
{
    /** Workflow stages that mean the application is still open (not finalized). */
    public const ACTIVE_STAGES = [
        'draft', 'fee_required', 'fees_payment', 'pending_review', 'under_review', 'changes_requested', 'suitability_review', 'application_approval',
        'not_approved', 'non_approval_reasons', 'admin_fee', 'admin_fee_paid', 'appeal_payment', 'appeal_review', 'pending_expiry',
    ];

    protected $table = 'applications';

    protected $primaryKey = 'crm_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'application_date' => 'date',
        'approved_date' => 'date',
        'not_approved_date' => 'date',
        'designation_expiry' => 'date',
        'review_completion_date' => 'date',
        'suitability_review_date' => 'date',
        'resubmission_date' => 'date',
        'total_due' => 'decimal:2',
        'eqa_good_standing' => 'boolean',
        'ptib_good_standing' => 'boolean',
        'designation_decision' => 'boolean',
        'receipt_confirmation' => 'boolean',
        'ready_for_review' => 'boolean',
        'fees_payment_received' => 'boolean',
        'process_completed' => 'boolean',
        'need_additional_details' => 'boolean',
        'appeal_successful' => 'boolean',
        'sabc_designation' => 'boolean',
        'reason_incomplete' => 'boolean',
        'reason_non_payment' => 'boolean',
        'reason_withdrawn' => 'boolean',
        'reason_not_good_standing' => 'boolean',
        'reason_not_meet_eligibility' => 'boolean',
        'program_associate_degree' => 'boolean',
        'program_university_transfer' => 'boolean',
        'program_bachelors_degree' => 'boolean',
        'program_graduate_degree' => 'boolean',
        'program_career_training' => 'boolean',
        'program_language_training' => 'boolean',
        'program_theological_education' => 'boolean',
        'program_trades_apprenticeship' => 'boolean',
        'media_pamphlet' => 'boolean',
        'media_website' => 'boolean',
        'media_brochure' => 'boolean',
        'media_poster' => 'boolean',
        'media_banner' => 'boolean',
        'media_billboard' => 'boolean',
        'affirm_policy_manual' => 'boolean',
        'affirm_website_compliance' => 'boolean',
        'affirm_written_permission' => 'boolean',
        'affirm_branding_guide' => 'boolean',
        'affirm_understands_comply' => 'boolean',
        'affirm_authorized' => 'boolean',
        'service_address_confirmed' => 'boolean',
        'per_student_fee_enrolment' => 'integer',
        'annual_report_filed' => 'boolean',
        'application_fee' => 'decimal:2',
        'annual_designation_fee' => 'decimal:2',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_crm_id', 'crm_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'application_crm_id', 'crm_id');
    }

    public function previousOperatingNames(): HasMany
    {
        return $this->hasMany(PreviousOperatingName::class, 'application_crm_id', 'crm_id')->orderByDesc('used_from');
    }

    public function campusLocations(): HasMany
    {
        return $this->hasMany(ApplicationCampusLocation::class, 'application_crm_id', 'crm_id')->orderBy('sort_order');
    }

    public function webCompliances(): HasMany
    {
        return $this->hasMany(ApplicationWebCompliance::class, 'application_crm_id', 'crm_id')->orderBy('section_order')->orderBy('item_order');
    }

    // Migrated projection stores plain dates; keep them as Y-m-d when serialized.
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }
}
