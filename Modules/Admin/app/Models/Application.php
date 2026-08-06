<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An EQA designation application, migrated from the legacy Dynamics
 * eqa_eqaapplication entity. Read-only projection used by both portals.
 */
class Application extends Model
{
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
        'application_fee' => 'decimal:2',
        'annual_designation_fee' => 'decimal:2',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_crm_id', 'crm_id');
    }
}
