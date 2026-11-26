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
use Modules\Admin\Services\ApplicationFeeService;
use Modules\Admin\Services\DesignationService;

/**
 * Drives the EQA application approval Business Process Flow (migrated from the
 * Dynamics BPF): Draft -> Application Fee Required -> Fee Payment -> Pending Review ->
 * Under Review [-> Additional Information Required -> Under Review] -> [Eligibility] ->
 * Application Approval | Not Approved -> Non-Approval Reasons | Suitability Review -> End.
 * Each action advances the stage and records the matching decision fields on the
 * application in postgres-dest.
 */
class ApplicationWorkflowController extends Controller
{
    /** Ways the ministry can record an application fee paid outside Express Pay. */
    private const MANUAL_PAYMENT_METHODS = ['EFT', 'Cheque', 'Other'];

    /**
     * stage => [action => next stage]. Only the institution can submit a draft (or resubmit an
     * application returned for additional information); the ministry can move its completion deadline.
     */
    private const TRANSITIONS = [
        'draft' => ['extend_completion' => 'draft'],
        'fee_required' => ['record_payment' => 'fees_payment', 'extend_completion' => 'fee_required'],
        'fees_payment' => ['confirm_payment' => 'pending_review'],
        'pending_review' => ['begin_review' => 'under_review'],
        'under_review' => [
            'eligible' => 'application_approval',
            'ineligible' => 'not_approved',
            'refer_suitability' => 'suitability_review',
            'request_changes' => 'changes_requested',
        ],
        'changes_requested' => ['extend_changes' => 'changes_requested'],
        'application_approval' => ['approve' => 'admin_fee'],
        'not_approved' => ['record_reasons' => 'non_approval_reasons'],
        'non_approval_reasons' => ['finalize' => 'end'],
        'suitability_review' => ['pass' => 'admin_fee', 'fail' => 'end'],
        'admin_fee' => ['confirm_admin_fee' => 'end'],
        'appeal_payment' => ['confirm_appeal_fee' => 'appeal_review'],
        'appeal_review' => ['appeal_overturn' => 'application_approval', 'appeal_uphold' => 'end'],
        'pending_expiry' => ['confirm_expiry' => 'end', 'extend_changes' => 'changes_requested'],
    ];

    /**
     * Required steps that gate advancing a stage, mirroring the Dynamics BPF
     * (processstage.clientdata IsRequired flags). stage => [field => label].
     */
    private const REQUIRED = [
        'fees_payment' => ['fees_payment_received' => 'Fees Payment Received?'],
        'pending_review' => [
            'receipt_confirmation' => 'Receipt Confirmation?',
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
            // sabc_designation is retired and read-only; older applications keep their stored value.
            'eligibility' => ['nullable', 'string', 'max:100'],
            'review_completion_date' => ['nullable', 'date'],
            // eqa_good_standing / ptib_good_standing are retired: not shown, never written.
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
            'affirm_authorized' => ['boolean'],
            'representative_signature' => ['nullable', 'string', 'max:255'],
        ]);

        // An application isn't complete until its fee is paid, so receipt can't be confirmed before then.
        if (($data['receipt_confirmation'] ?? false) && in_array($application->workflow_stage ?: 'draft', ['draft', 'fee_required'], true)) {
            return back()->withErrors(['receipt_confirmation' => 'Receipt can only be confirmed after the application fee is paid.'])
                ->with('error', 'Receipt can only be confirmed after the application fee is paid.');
        }

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

        $fees = app(ApplicationFeeService::class);
        $advanced = 'Application advanced to '.str_replace('_', ' ', $next).'.';

        switch ($action) {
            case 'request_changes':
                $data = $request->validate(
                    ['changes' => ['required', 'string', 'max:2000'], 'changes_due_date' => $this->dueDateRules(false)],
                    $this->changesDueDateMessages(),
                );
                $fees->requestChanges($application, $data['changes'], $data['changes_due_date'] ?? null);

                return back()->with('success', 'Additional information requested; the institution has been notified.');
            case 'extend_changes':
                $data = $request->validate(['changes_due_date' => $this->dueDateRules(true)], $this->changesDueDateMessages());
                $fees->extendChangesDeadline($application, $data['changes_due_date']);

                return back()->with('success', 'Resubmission deadline set to '.$data['changes_due_date'].'; the institution has been notified.');
            case 'extend_completion':
                $data = $request->validate(['completion_due_date' => $this->dueDateRules(true)], [
                    'completion_due_date.required' => 'Choose the date the institution must complete the application by.',
                    'completion_due_date.after' => 'The completion deadline must be after today.',
                    'completion_due_date.before_or_equal' => 'The completion deadline must be within one year.',
                ]);
                $fees->extendCompletionDeadline($application, $data['completion_due_date']);

                return back()->with('success', 'Completion deadline set to '.$data['completion_due_date'].'.');
            case 'approve':
            case 'pass':
                $data = $request->validate([
                    'compliance_order' => ['boolean'],
                    'compliance_order_notes' => ['nullable', 'string', 'max:2000', 'required_if_accepted:compliance_order'],
                ]);
                $invoice = $fees->approve(
                    $application,
                    (bool) ($data['compliance_order'] ?? false),
                    (string) ($data['compliance_order_notes'] ?? ''),
                );

                $institution = DB::table('institutions')->where('crm_id', $application->institution_crm_id)->first();
                $designated = ($institution->eqa_status ?? null) === 'Designated';

                return back()->with('success', $invoice->invoice_status === 'Paid'
                    ? 'Application approved. No administrative fee applies'.($designated ? ', so the designation has been granted.' : '.')
                    : 'Application approved'.($designated ? ' and the institution is Designated' : '').'. Administrative fee invoice '.$invoice->invoice_number.' ($'.number_format((float) $invoice->total_charges, 2).') sent to the institution.');
            case 'confirm_admin_fee':
            case 'confirm_appeal_fee':
                $invoice = $fees->invoiceFor($crmId, $action === 'confirm_admin_fee' ? ApplicationFeeService::FEE_ADMIN_VARIABLE : ApplicationFeeService::FEE_APPEAL);
                if ($invoice === null) {
                    return back()->with('error', 'No invoice found for this fee.');
                }
                [$method, $details] = $this->manualPayment($request);
                $fees->recordManualPayment($invoice, $method, $details);

                return back()->with('success', 'Payment confirmed. '.$advanced);
            case 'appeal_overturn':
                $fees->overturnAppeal($application);

                return back()->with('success', 'Appeal overturned. Record the approval decision.');
            case 'appeal_uphold':
                $fees->upholdAppeal($application);

                return back()->with('success', 'Appeal upheld. The application is Declined.');
        }

        $today = Carbon::now()->toDateString();
        $updates = ['workflow_stage' => $next];

        switch ($action) {
            case 'record_payment':
                [$method, $details] = $this->manualPayment($request);
                $feeInvoice = $fees->invoiceFor($crmId, ApplicationFeeService::APPLICATION_FEES);
                if ($feeInvoice === null) {
                    // Applications submitted before the online fee existed: legacy CRM financial summary.
                    $this->ensureInvoice($application);
                    $feeInvoice = DB::table('invoices')->where('application_crm_id', $crmId)->orderByDesc('invoice_number')->first();
                }
                if ($feeInvoice !== null) {
                    $fees->recordManualPayment($feeInvoice, $method, $details);
                }
                $updates['status'] = ApplicationFeeService::STATUS_FEE_RECEIVED;
                $updates['fees_payment_received'] = true;
                break;
            case 'confirm_payment':
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
                break;
            case 'refer_suitability':
                $updates['status'] = 'Suitability Review';
                $updates['eligibility'] = 'Suitability Review Needed';
                $updates['suitability_review_date'] = $today;
                break;
            case 'record_reasons':
                $updates['non_approval_reasons'] = (string) $request->string('reasons');
                break;
            case 'confirm_expiry':
                // Missed resubmission deadline confirmed by the ministry.
                $updates['status'] = 'Expired';
                break;
            case 'finalize':
            case 'fail':
                $updates['status'] = 'Not Approved';
                $updates['designation_decision'] = false;
                $updates['not_approved_date'] = $today;
                $updates['appeal_deadline'] = Carbon::now('America/Vancouver')->addDays((int) config('eqa.deadlines.appeal_days', 30))->toDateString();
                break;
        }

        DB::transaction(function () use ($crmId, $updates, $action, $application): void {
            DB::table('applications')->where('crm_id', $crmId)->update($updates);

            // Approval designates once the admin variable fee is paid (ApplicationFeeService);
            // a decline cascades onto the institution's EQA Status immediately.
            if (in_array($action, ['finalize', 'fail'], true)) {
                app(DesignationService::class)->onApplicationNotApproved($application);
            }
        });

        // No decline email: the institution receives a formal decision letter outside the system.

        return back()->with('success', 'Application advanced to '.str_replace('_', ' ', $next).'.');
    }

    /** @return array<int, string> */
    private function dueDateRules(bool $required): array
    {
        $today = Carbon::now('America/Vancouver');

        return [
            $required ? 'required' : 'nullable',
            'date_format:Y-m-d',
            'after:'.$today->toDateString(),
            'before_or_equal:'.$today->copy()->addYear()->toDateString(),
        ];
    }

    /** @return array<string, string> */
    private function changesDueDateMessages(): array
    {
        return [
            'changes_due_date.required' => 'Choose the date the institution must resubmit by.',
            'changes_due_date.after' => 'The resubmission deadline must be after today.',
            'changes_due_date.before_or_equal' => 'The resubmission deadline must be within one year.',
        ];
    }

    private function truthy($value): bool
    {
        return $value === true || $value === 't' || $value === 1 || $value === '1';
    }

    /**
     * Details the ministry enters when recording a payment received outside Express Pay.
     *
     * @return array{0: string, 1: array{date: ?string, reference: ?string, notes: ?string}}
     */
    private function manualPayment(Request $request): array
    {
        $data = $request->validate([
            'payment_method' => ['nullable', 'in:'.implode(',', self::MANUAL_PAYMENT_METHODS)],
            'payment_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.Carbon::now('America/Vancouver')->toDateString()],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'payment_notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'payment_date.before_or_equal' => 'The payment date cannot be in the future.',
        ]);

        return [($data['payment_method'] ?? null) ?: 'Other', [
            'date' => $data['payment_date'] ?? null,
            'reference' => isset($data['payment_reference']) ? trim($data['payment_reference']) : null,
            'notes' => isset($data['payment_notes']) ? trim($data['payment_notes']) : null,
        ]];
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

        $number = 'INV-'.str_pad((string) app(ApplicationFeeService::class)->nextNumber('invoices', 'invoice_number'), 8, '0', STR_PAD_LEFT);

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
