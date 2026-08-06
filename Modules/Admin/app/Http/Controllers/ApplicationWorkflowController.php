<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Drives the EQA application approval Business Process Flow (migrated from the
 * Dynamics BPF): Draft -> Pending Review -> Fees Payment -> Under Review ->
 * [Eligibility] -> Application Approval | Not Approved -> Non-Approval Reasons |
 * Suitability Review -> End. Each action advances the stage and records the
 * matching decision fields on the application in postgres-dest.
 */
class ApplicationWorkflowController extends Controller
{
    /** stage => [action => next stage]. */
    private const TRANSITIONS = [
        'draft' => ['submit' => 'pending_review'],
        'pending_review' => ['record_payment' => 'fees_payment'],
        'fees_payment' => ['begin_review' => 'under_review'],
        'under_review' => [
            'eligible' => 'application_approval',
            'ineligible' => 'not_approved',
            'refer_suitability' => 'suitability_review',
        ],
        'application_approval' => ['approve' => 'end'],
        'not_approved' => ['record_reasons' => 'non_approval_reasons'],
        'non_approval_reasons' => ['finalize' => 'end'],
        'suitability_review' => ['pass' => 'end', 'fail' => 'end'],
    ];

    /**
     * Required steps that gate advancing a stage, mirroring the Dynamics BPF
     * (processstage.clientdata IsRequired flags). stage => [field => label].
     */
    private const REQUIRED = [
        'pending_review' => ['receipt_confirmation' => 'Receipt Confirmation?'],
        'fees_payment' => [
            'fees_payment_received' => 'Fees Payment Received?',
            'ready_for_review' => 'Ready to Review?',
        ],
    ];

    /** Ministry edits the application fields while it is pending approval. */
    public function update(Request $request, string $crmId): RedirectResponse
    {
        $application = DB::table('applications')->where('crm_id', $crmId)->first();
        abort_if($application === null, 404);

        $data = $request->validate([
            'receipt_confirmation' => ['boolean'],
            'fees_payment_received' => ['boolean'],
            'ready_for_review' => ['boolean'],
            'sabc_designation' => ['boolean'],
            'eligibility' => ['nullable', 'string', 'max:100'],
            'review_completion_date' => ['nullable', 'date'],
            'eqa_good_standing' => ['boolean'],
            'ptib_good_standing' => ['boolean'],
            'need_additional_details' => ['boolean'],
            'suitability_review_date' => ['nullable', 'date'],
            'resubmission_date' => ['nullable', 'date'],
            'approved_date' => ['nullable', 'date'],
            'designation_expiry' => ['nullable', 'date'],
            'not_approved_date' => ['nullable', 'date'],
            'appeal_successful' => ['boolean'],
            'reason_incomplete' => ['boolean'],
            'reason_non_payment' => ['boolean'],
            'reason_withdrawn' => ['boolean'],
            'reason_not_good_standing' => ['boolean'],
            'reason_not_meet_eligibility' => ['boolean'],
            'program_associate_degree' => ['boolean'],
            'program_university_transfer' => ['boolean'],
            'program_bachelors_degree' => ['boolean'],
            'program_graduate_degree' => ['boolean'],
            'program_career_training' => ['boolean'],
            'program_language_training' => ['boolean'],
            'program_theological_education' => ['boolean'],
            'program_trades_apprenticeship' => ['boolean'],
            'media_pamphlet' => ['boolean'],
            'media_website' => ['boolean'],
            'media_brochure' => ['boolean'],
            'media_poster' => ['boolean'],
            'media_banner' => ['boolean'],
            'media_billboard' => ['boolean'],
            'brand_usage_plan' => ['nullable', 'string', 'max:2000'],
            'other_logos_trademarks' => ['nullable', 'string', 'max:2000'],
            'affiliates_partners' => ['nullable', 'string', 'max:2000'],
            'affirm_policy_manual' => ['boolean'],
            'affirm_website_compliance' => ['boolean'],
            'affirm_written_permission' => ['boolean'],
            'affirm_branding_guide' => ['boolean'],
            'affirm_understands_comply' => ['boolean'],
            'affirm_authorized' => ['boolean'],
            'representative_signature' => ['nullable', 'string', 'max:255'],
            'application_fee' => ['nullable', 'numeric', 'min:0'],
            'annual_designation_fee' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::table('applications')->where('crm_id', $crmId)->update($data);

        return back()->with('success', 'Application details saved.');
    }

    public function advance(Request $request, string $crmId): RedirectResponse
    {
        $application = DB::table('applications')->where('crm_id', $crmId)->first();
        abort_if($application === null, 404);

        $stage = $application->workflow_stage ?: 'draft';
        $action = (string) $request->string('action');
        $next = self::TRANSITIONS[$stage][$action] ?? null;

        if ($next === null) {
            return back()->with('error', 'That action is not available at the current stage.');
        }

        $missing = [];
        foreach (self::REQUIRED[$stage] ?? [] as $field => $label) {
            if (! $this->truthy($application->{$field} ?? null)) {
                $missing[] = $label;
            }
        }
        if ($missing !== []) {
            return back()->with('error', 'You have to complete the required steps before you can advance: '.implode(', ', $missing).'.');
        }

        $today = Carbon::now()->toDateString();
        $updates = ['workflow_stage' => $next];

        switch ($action) {
            case 'record_payment':
                $this->ensureInvoice($application);
                break;
            case 'submit':
                $updates['status'] = 'Pending Review';
                break;
            case 'begin_review':
                $updates['status'] = 'Under Review';
                break;
            case 'eligible':
            case 'ineligible':
                $updates['status'] = 'Under Review';
                $updates['eligibility'] = $action === 'eligible' ? 'Requirements Met' : 'Requirements Not Met';
                $updates['review_completion_date'] = $today;
                $updates['eqa_good_standing'] = $request->boolean('eqa_good_standing');
                $updates['ptib_good_standing'] = $request->boolean('ptib_good_standing');
                break;
            case 'refer_suitability':
                $updates['status'] = 'Suitability Review';
                $updates['eligibility'] = 'Suitability Review Needed';
                $updates['suitability_review_date'] = $today;
                break;
            case 'approve':
            case 'pass':
                $updates['status'] = 'Approved';
                $updates['designation_decision'] = true;
                $updates['approved_date'] = $today;
                if (empty($application->designation_expiry)) {
                    $updates['designation_expiry'] = Carbon::now()->addYear()->toDateString();
                }
                break;
            case 'record_reasons':
                $updates['non_approval_reasons'] = (string) $request->string('reasons');
                break;
            case 'finalize':
            case 'fail':
                $updates['status'] = 'Not Approved';
                $updates['designation_decision'] = false;
                $updates['not_approved_date'] = $today;
                break;
        }

        DB::table('applications')->where('crm_id', $crmId)->update($updates);

        return back()->with('success', 'Application advanced to '.str_replace('_', ' ', $next).'.');
    }

    private function truthy($value): bool
    {
        return $value === true || $value === 't' || $value === 1 || $value === '1';
    }

    /**
     * Generate the financial-summary invoice for an application, mirroring the
     * Dynamics "Create New Financial Summary" + "Invoice Auto Numbering"
     * workflows. No-op if one already exists.
     */
    private function ensureInvoice(object $application): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }
        if (DB::table('invoices')->where('application_crm_id', $application->crm_id)->exists()) {
            return;
        }

        $maxNum = (int) DB::table('invoices')
            ->selectRaw("MAX(NULLIF(regexp_replace(invoice_number, '[^0-9]', '', 'g'), '')::bigint) AS m")
            ->value('m');
        $number = 'INV-'.str_pad((string) ($maxNum + 1), 8, '0', STR_PAD_LEFT);

        $amount = (float) ($application->total_due ?? 0);
        if ($amount <= 0) {
            $amount = (float) ($application->application_fee ?? 0) + (float) ($application->annual_designation_fee ?? 0);
        }
        $paid = $this->truthy($application->fees_payment_received ?? null);

        DB::table('invoices')->insert([
            'crm_id' => (string) Str::uuid(),
            'application_crm_id' => $application->crm_id,
            'application_reference' => $application->reference,
            'institution_crm_id' => $application->institution_crm_id,
            'institution_name' => $application->institution_name,
            'invoice_number' => $number,
            'invoice_date' => Carbon::now()->toDateString(),
            'invoice_amount' => $amount,
            'taxes' => 0,
            'total_charges' => $amount,
            'invoice_balance' => $paid ? 0 : $amount,
            'invoice_status' => $paid ? 'Paid' : 'Unpaid',
            'status' => 'Active',
        ]);

        DB::table('applications')->where('crm_id', $application->crm_id)->update(['invoice_number' => $number]);
    }
}
