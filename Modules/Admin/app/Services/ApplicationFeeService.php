<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use App\Services\ExpressPayService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fee invoices and the payment-driven steps of the EQA workflow (flowchart DRAFT V1.1):
 * application fee -> submission, admin variable fee -> designation, appeal fee -> appeal
 * review, plus the deadlines that expire unpaid / unanswered applications.
 */
class ApplicationFeeService
{
    public const FEE_APPLICATION_NEW = 'application_new';

    public const FEE_APPLICATION_RENEWAL = 'application_renewal';

    public const FEE_ADMIN_VARIABLE = 'admin_variable';

    public const FEE_APPEAL = 'appeal';

    public const APPLICATION_FEES = [self::FEE_APPLICATION_NEW, self::FEE_APPLICATION_RENEWAL];

    private const FEE_LABELS = [
        self::FEE_APPLICATION_NEW => 'New Application Fee',
        self::FEE_APPLICATION_RENEWAL => 'Renewal Application Fee',
        self::FEE_ADMIN_VARIABLE => 'Administrative Fee',
        self::FEE_APPEAL => 'Appeal Fee',
    ];

    public const STATUS_FEE_REQUIRED = 'Application Fee Required';

    public const STATUS_FEE_RECEIVED = 'Fee Payment Received';

    /** Payment method recorded on the $0 invoices of a fee exempt institution. */
    public const METHOD_FEE_EXEMPT = 'Fee Exempt';

    /** The only QA Met Through pathway that is not a private institution (no multiple location fee). */
    public const PUBLIC_QA_MET_THROUGH = 'Public Institution Legislation';

    public function __construct(
        private readonly ProcessNotifier $notifier,
        private readonly DesignationService $designation,
    ) {}

    /** One more than the highest number embedded in $column (references like APP-00000012). */
    public function nextNumber(string $table, string $column): int
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $max = (int) DB::table($table)
                ->selectRaw("MAX(NULLIF(regexp_replace({$column}, '[^0-9]', '', 'g'), '')::bigint) AS m")
                ->value('m');
        } else {
            $max = (int) DB::table($table)->pluck($column)
                ->map(fn ($value): int => (int) preg_replace('/\D/', '', (string) $value))
                ->max();
        }

        return $max + 1;
    }

    /** @param  list<array<string, mixed>>  $lineItems */
    public function createInvoice(object $application, string $feeType, float $amount, ?string $dueDate = null, ?string $description = null, array $lineItems = []): object
    {
        $crmId = (string) Str::uuid();
        $amount = round($amount, 2);

        DB::table('invoices')->insert([
            'crm_id' => $crmId,
            'application_crm_id' => $application->crm_id,
            'application_reference' => $application->reference ?? null,
            'institution_crm_id' => $application->institution_crm_id ?? null,
            'institution_name' => $application->institution_name ?? null,
            'invoice_number' => 'INV-'.str_pad((string) $this->nextNumber('invoices', 'invoice_number'), 8, '0', STR_PAD_LEFT),
            'invoice_date' => $this->today(),
            'invoice_amount' => $amount,
            'taxes' => 0,
            'total_charges' => $amount,
            'invoice_balance' => $amount,
            'invoice_status' => 'Unpaid',
            'status' => 'Active',
            'fee_type' => $feeType,
            'due_date' => $dueDate,
            'description' => $description,
            'line_items' => $lineItems !== [] ? json_encode($lineItems) : null,
        ]);

        return DB::table('invoices')->where('crm_id', $crmId)->first();
    }

    /** @param  array{amount: float, description: string, line_items: list<array<string, mixed>>}  $quote */
    private function createQuotedInvoice(object $application, string $feeType, array $quote, ?string $dueDate = null): object
    {
        return $this->createInvoice($application, $feeType, $quote['amount'], $dueDate, $quote['description'], $quote['line_items']);
    }

    /** @return array{amount: float, description: string, line_items: list<array<string, mixed>>} */
    public function applicationFeeQuote(string $feeType, ?object $institution = null): array
    {
        $label = $feeType === self::FEE_APPLICATION_RENEWAL ? 'Renewal Application Fee' : 'New Application Fee';
        if ($this->isFeeExempt($institution)) {
            return $this->waivedQuote($feeType, $label);
        }

        return $this->quote([$this->line($feeType, $label.' (fixed)', 1, (float) config('eqa.fees.'.$feeType))]);
    }

    /** @return array{amount: float, description: string, line_items: list<array<string, mixed>>} */
    public function appealFeeQuote(?object $institution = null): array
    {
        if ($this->isFeeExempt($institution)) {
            return $this->waivedQuote(self::FEE_APPEAL, 'Appeal Fee');
        }

        return $this->quote([$this->line(self::FEE_APPEAL, 'Appeal Fee (fixed)', 1, (float) config('eqa.fees.appeal'))]);
    }

    /** Fee exempt institutions (e.g. Indigenous institutions exempt under the Act) are never charged any fee. */
    public function isFeeExempt(?object $institution): bool
    {
        return $institution !== null && $this->truthy($institution->fee_exempt ?? null);
    }

    /** @return array{amount: float, description: string, line_items: list<array<string, mixed>>} */
    private function waivedQuote(string $code, string $label): array
    {
        return $this->quote([$this->line($code, $label.' (waived, fee exempt institution)', 1, 0.0)]);
    }

    /**
     * Administrative fee: multiple location fee (private institutions with more than one active
     * location) plus a per international student fee (study permit students, from the application).
     *
     * @return array{amount: float, description: string, line_items: list<array<string, mixed>>}
     */
    public function adminFeeQuote(object $application): array
    {
        $institution = $this->institution($application);
        if ($this->isFeeExempt($institution)) {
            return $this->waivedQuote(self::FEE_ADMIN_VARIABLE, 'Administrative Fee');
        }
        $qaMetThrough = trim((string) ($institution->qa_met_through ?? ''));
        $private = $qaMetThrough !== self::PUBLIC_QA_MET_THROUGH;
        $locations = $institution !== null
            ? DB::table('campuses')->where('institution_crm_id', $institution->crm_id)->where('status', 'Active')->count()
            : 0;
        // Per Student Fee Payment Enrollment (study permit students over the preceding calendar year); applications
        // from before that question fall back to the enrolment section's International Students - Study Permit.
        $permit = (int) ($application->per_student_fee_enrolment ?? $application->intl_students_permit ?? 0);
        $locationText = $locations.' active location'.($locations === 1 ? '' : 's');

        $lines = [];
        $notes = [];
        if ($private && $locations > 1) {
            $lines[] = $this->line('multi_location', 'Multiple Location Fee (private institution, '.$locationText.')', 1, (float) config('eqa.fees.multi_location'));
        } else {
            $notes[] = 'Multiple Location Fee not applied ('.($private ? 'private institution, '.$locationText : 'public institution').')';
        }
        if ($permit > 0) {
            $lines[] = $this->line('international_students', 'International Student Fee (study permit students)', $permit, (float) config('eqa.fees.per_international_student'));
        } else {
            $notes[] = 'International Student Fee not applied (0 study permit students)';
        }

        return $this->quote($lines, $notes);
    }

    /** @return array<string, mixed> */
    private function line(string $code, string $label, int $quantity, float $unitPrice): array
    {
        return [
            'code' => $code,
            'label' => $label,
            'quantity' => $quantity,
            'unit_price' => round($unitPrice, 2),
            'amount' => round($quantity * $unitPrice, 2),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  list<string>  $notes
     * @return array{amount: float, description: string, line_items: list<array<string, mixed>>}
     */
    private function quote(array $lines, array $notes = []): array
    {
        $money = static fn (float $v): string => '$'.number_format($v, 2);
        $text = array_map(
            fn (array $l): string => $l['quantity'] === 1
                ? $l['label'].': '.$money($l['amount'])
                : $l['label'].': '.$l['quantity'].' x '.$money($l['unit_price']).' = '.$money($l['amount']),
            $lines,
        );

        return [
            'amount' => round(array_sum(array_column($lines, 'amount')), 2),
            'description' => implode("\n", array_merge($text, $notes)),
            'line_items' => $lines,
        ];
    }

    /** @param  string|array<int, string>  $feeTypes */
    public function invoiceFor(string $applicationCrmId, string|array $feeTypes): ?object
    {
        return DB::table('invoices')
            ->where('application_crm_id', $applicationCrmId)
            ->whereIn('fee_type', (array) $feeTypes)
            ->orderByDesc('invoice_number')
            ->first();
    }

    /**
     * Ministry "Confirm Payment" for a payment made outside Express Pay (cheque, EFT, ...).
     *
     * @param  array{date?: ?string, reference?: ?string, notes?: ?string}  $details  as entered by the ministry
     */
    public function recordManualPayment(object $invoice, string $method = 'Manual', array $details = []): void
    {
        if (($invoice->invoice_status ?? null) !== 'Paid') {
            $due = (float) ($invoice->invoice_balance ?? $invoice->total_charges ?? 0);
            $update = [
                'invoice_balance' => 0,
                'invoice_status' => 'Paid',
                'payment_amount' => round((float) ($invoice->payment_amount ?? 0) + $due, 2),
                'payment_received_date' => ($details['date'] ?? null) ?: $this->today(),
                'payment_method' => $method,
            ];
            if (($details['reference'] ?? null) !== null && $details['reference'] !== '') {
                $update['transaction_reference'] = $details['reference'];
            }
            if (($details['notes'] ?? null) !== null && $details['notes'] !== '') {
                $update['payment_notes'] = $details['notes'];
            }
            DB::table('invoices')->where('crm_id', $invoice->crm_id)->update($update);
        }

        $this->invoicePaid($invoice->crm_id);
    }

    /** Advance the application once one of its workflow invoices is fully paid. */
    public function invoicePaid(string $invoiceCrmId): void
    {
        $invoice = DB::table('invoices')->where('crm_id', $invoiceCrmId)->first();
        if ($invoice === null || empty($invoice->application_crm_id) || $invoice->invoice_status !== 'Paid') {
            return;
        }

        $application = DB::table('applications')->where('crm_id', $invoice->application_crm_id)->first();
        if ($application === null) {
            return;
        }

        $this->sendReceipt($invoice, $application);

        switch ($invoice->fee_type ?? null) {
            case self::FEE_APPLICATION_NEW:
            case self::FEE_APPLICATION_RENEWAL:
                DB::table('applications')->where('crm_id', $application->crm_id)->update(['fees_payment_received' => true]);
                if ($application->workflow_stage === 'draft') {
                    $this->submit($application);
                } elseif ($application->workflow_stage === 'fee_required') {
                    $this->feeReceived($application);
                }
                break;

            case self::FEE_ADMIN_VARIABLE:
                if ($application->workflow_stage === 'admin_fee') {
                    $this->completeDesignation($application);
                }
                break;

            case self::FEE_APPEAL:
                if ($application->workflow_stage === 'appeal_payment') {
                    DB::table('applications')->where('crm_id', $application->crm_id)->update([
                        'workflow_stage' => 'appeal_review',
                        'appeal_date' => $this->today(),
                    ]);
                    $institution = $this->institution($application);
                    if ($institution !== null) {
                        $this->notifier->appealSubmitted(
                            $institution,
                            DB::table('applications')->where('crm_id', $application->crm_id)->first(),
                            DB::table('invoices')->where('crm_id', $invoice->crm_id)->first(),
                        );
                    }
                }
                break;

            default:
                // Legacy financial-summary invoices only flag the fee as received.
                DB::table('applications')->where('crm_id', $application->crm_id)->update(['fees_payment_received' => true]);
        }
    }

    /**
     * Draft -> Application Fee Required (or Fee Payment when the fee is already paid);
     * an application returned for additional information goes straight back to Under Review.
     * The receipt acknowledgement is only sent once the fee is paid, and Pending Review only
     * follows the ministry's confirmation.
     */
    public function submit(object $application): void
    {
        if (($application->workflow_stage ?? null) === 'changes_requested') {
            DB::table('applications')->where('crm_id', $application->crm_id)->update([
                'workflow_stage' => 'under_review',
                'status' => 'Under Review',
                'need_additional_details' => false,
                'resubmission_date' => $this->today(),
            ]);

            return;
        }

        DB::table('applications')->where('crm_id', $application->crm_id)->update([
            'workflow_stage' => 'fee_required',
            'status' => self::STATUS_FEE_REQUIRED,
        ]);

        $institution = $this->institution($application);
        $feeInvoice = $this->invoiceFor($application->crm_id, self::APPLICATION_FEES)
            ?? $this->createApplicationFeeInvoice($application, $institution);

        // Nothing to pay (fee exempt institution): settling the $0 invoice completes the application,
        // and with no payment for the ministry to confirm it goes straight to Pending Review.
        if ($this->settleIfNothingDue($feeInvoice, $institution)) {
            $this->skipFeeConfirmation((string) $application->crm_id);

            return;
        }

        if ($feeInvoice->invoice_status === 'Paid') {
            $this->feeReceived($application);
        }
    }

    /**
     * Settle an unpaid invoice with nothing due (no charge, or a fee exempt institution) so the
     * workflow moves on as if it were paid. Returns whether it was settled.
     */
    private function settleIfNothingDue(object $invoice, ?object $institution): bool
    {
        if (($invoice->invoice_status ?? null) === 'Paid' || (float) ($invoice->total_charges ?? 0) > 0) {
            return false;
        }

        $this->recordManualPayment($invoice, $this->isFeeExempt($institution) ? self::METHOD_FEE_EXEMPT : 'No Charge');

        return true;
    }

    /**
     * The institution was just marked fee exempt: waive every invoice it still owes. Each one is
     * rewritten to $0 (the original charge is kept in the payment notes), settled as Fee Exempt and
     * its application moves on as if it were paid. Paid invoices are left alone (any refund happens
     * outside the system), as are invoices whose Express Pay payment is under review.
     *
     * @return array{waived: int, skipped: int}
     */
    public function waiveOutstandingInvoices(string $institutionCrmId): array
    {
        $expressPay = app(ExpressPayService::class);
        $counts = ['waived' => 0, 'skipped' => 0];

        $outstanding = DB::table('invoices')
            ->where('institution_crm_id', $institutionCrmId)
            ->whereNotIn('invoice_status', ['Paid', 'Expired'])
            ->orderBy('invoice_number')
            ->get();

        foreach ($outstanding as $invoice) {
            $due = $expressPay->amountDue($invoice);
            if ($due <= 0) {
                continue;
            }
            if (! $expressPay->isPayable($invoice)) {
                $counts['skipped']++;

                continue;
            }

            $quote = $this->waivedQuote((string) ($invoice->fee_type ?: 'fee'), self::FEE_LABELS[$invoice->fee_type] ?? 'Fee');
            $notes = 'Waived on '.$this->today().': institution marked fee exempt (originally $'.number_format($due, 2).').';
            DB::table('invoices')->where('crm_id', $invoice->crm_id)->update([
                'invoice_amount' => 0,
                'taxes' => 0,
                'total_charges' => 0,
                'invoice_balance' => 0,
                'description' => $quote['description'],
                'line_items' => json_encode($quote['line_items']),
            ]);
            if (in_array($invoice->fee_type, self::APPLICATION_FEES, true)) {
                DB::table('applications')->where('crm_id', $invoice->application_crm_id)->update(['application_fee' => 0, 'total_due' => 0]);
            }

            $this->recordManualPayment(
                DB::table('invoices')->where('crm_id', $invoice->crm_id)->first(),
                self::METHOD_FEE_EXEMPT,
                ['notes' => trim(($invoice->payment_notes ? $invoice->payment_notes."\n" : '').$notes)],
            );
            if (in_array($invoice->fee_type, self::APPLICATION_FEES, true)) {
                $this->skipFeeConfirmation((string) $invoice->application_crm_id);
            }
            $counts['waived']++;
        }

        return $counts;
    }

    /** A waived application fee leaves no payment for the ministry to confirm: Fee Payment -> Pending Review. */
    private function skipFeeConfirmation(string $applicationCrmId): void
    {
        DB::table('applications')
            ->where('crm_id', $applicationCrmId)
            ->where('workflow_stage', 'fees_payment')
            ->update(['workflow_stage' => 'pending_review', 'status' => 'Pending Review']);
    }

    /**
     * Application fee paid (online or recorded by the ministry): the application is now complete,
     * so acknowledge receipt to the institution and wait for the ministry to confirm the payment.
     */
    public function feeReceived(object $application): void
    {
        DB::table('applications')->where('crm_id', $application->crm_id)->update([
            'workflow_stage' => 'fees_payment',
            'status' => self::STATUS_FEE_RECEIVED,
            'fees_payment_received' => true,
        ]);

        $institution = $this->institution($application);
        $feeInvoice = $this->invoiceFor($application->crm_id, self::APPLICATION_FEES);
        if ($institution === null || $feeInvoice === null) {
            return;
        }

        $isReapplication = $feeInvoice->fee_type === self::FEE_APPLICATION_RENEWAL;
        $reference = (string) $application->reference;
        // Applications submitted before this rule were acknowledged on submission; don't send it twice.
        $alreadySent = $reference !== '' && DB::table('sent_emails')
            ->where('template_key', $isReapplication ? 'reapplication_submission' : 'application_submission')
            ->where('institution_crm_id', $institution->crm_id)
            ->where('status', 'sent')
            ->where('body', 'like', '%'.$reference.'%')
            ->exists();
        if (! $alreadySent) {
            $this->notifier->applicationSubmitted($institution, $reference, $isReapplication);
        }
    }

    /** A draft that has never been submitted: no fees are requested until it is. */
    public function awaitingSubmission(object $application): bool
    {
        return ($application->workflow_stage ?? null) === 'draft';
    }

    /** The institution may edit and (re)submit a new draft or an application returned for additional information. */
    public function editableByInstitution(object $application): bool
    {
        return ($application->status ?? null) === 'Draft' || ($application->workflow_stage ?? null) === 'changes_requested';
    }

    private function createApplicationFeeInvoice(object $application, ?object $institution): object
    {
        $feeType = $institution !== null && $this->notifier->isReapplication($institution)
            ? self::FEE_APPLICATION_RENEWAL
            : self::FEE_APPLICATION_NEW;
        $quote = $this->applicationFeeQuote($feeType, $institution);

        DB::table('applications')->where('crm_id', $application->crm_id)->update([
            'application_fee' => $quote['amount'],
            'total_due' => $quote['amount'],
        ]);

        return $this->createQuotedInvoice($application, $feeType, $quote);
    }

    public const STATUS_INFO_REQUIRED = 'Additional Information Required';

    public const STATUS_PENDING_EXPIRY = 'Pending Admin Confirm Expiry';

    /** Default "resubmit by" date when the ministry reopens an application for changes (Maintenance > Utils). */
    public function defaultChangesDueDate(): string
    {
        return Carbon::now('America/Vancouver')->addDays((int) config('eqa.deadlines.changes_days', 30))->toDateString();
    }

    /**
     * Ministry returns an incomplete application so the institution can supply missing information
     * (stage changes_requested, status Additional Information Required).
     */
    public function requestChanges(object $application, string $changes, ?string $dueDate = null): void
    {
        $due = $dueDate ?: $this->defaultChangesDueDate();

        DB::table('applications')->where('crm_id', $application->crm_id)->update([
            'workflow_stage' => 'changes_requested',
            'status' => self::STATUS_INFO_REQUIRED,
            // Legacy CRM "Need Additional Details" field, kept in step for reporting.
            'need_additional_details' => true,
            'changes_requested' => $changes,
            'changes_due_date' => $due,
        ]);

        $institution = $this->institution($application);
        if ($institution !== null) {
            $this->notifier->changesRequested($institution, $application, $changes, $due);
        }
    }

    /**
     * Ministry moves the resubmission deadline of an application waiting on additional information,
     * or reopens one that missed it (Pending Admin Confirm Expiry). The institution is told the new date.
     */
    public function extendChangesDeadline(object $application, string $dueDate): void
    {
        DB::table('applications')->where('crm_id', $application->crm_id)->update([
            'workflow_stage' => 'changes_requested',
            'status' => self::STATUS_INFO_REQUIRED,
            'need_additional_details' => true,
            'changes_due_date' => $dueDate,
        ]);

        $institution = $this->institution($application);
        if ($institution !== null) {
            $this->notifier->changesRequested($institution, $application, (string) ($application->changes_requested ?? ''), $dueDate);
        }
    }

    /** Approval (optionally under a compliance order): raise the administrative fee invoice and send the decision (not after an appeal). */
    public function approve(object $application, bool $complianceOrder, string $complianceNotes): object
    {
        $today = $this->today();
        $due = Carbon::now('America/Vancouver')->addDays((int) config('eqa.deadlines.admin_fee_days', 30))->toDateString();
        $quote = $this->adminFeeQuote($application);

        DB::table('applications')->where('crm_id', $application->crm_id)->update([
            'workflow_stage' => 'admin_fee',
            'status' => $complianceOrder ? 'Approved (Under Compliance Order)' : 'Approved',
            'designation_decision' => true,
            'approved_date' => $today,
            'designation_expiry' => $application->designation_expiry ?: Carbon::now('America/Vancouver')->addYear()->toDateString(),
            'compliance_order' => $complianceOrder,
            'compliance_order_notes' => $complianceOrder ? $complianceNotes : null,
        ]);

        $application = DB::table('applications')->where('crm_id', $application->crm_id)->first();
        $invoice = $this->createQuotedInvoice($application, self::FEE_ADMIN_VARIABLE, $quote, $due);

        // Nothing to pay (e.g. public institution with no international students, or fee exempt): designate now.
        if ($this->settleIfNothingDue($invoice, $this->institution($application))) {
            return DB::table('invoices')->where('crm_id', $invoice->crm_id)->first();
        }

        $institution = $this->institution($application);
        if ($institution !== null && ! $this->decidedOnAppeal($application)) {
            $this->notifier->approvedFeeDue($institution, $application, $invoice, $complianceOrder, $complianceNotes);
        }

        return $invoice;
    }

    public function canAppeal(object $application): bool
    {
        return $application->status === 'Not Approved'
            && $application->workflow_stage === 'end'
            && ! empty($application->appeal_deadline)
            && $this->today() <= (string) $application->appeal_deadline
            && empty($application->appeal_decision);
    }

    /** Institution chooses to appeal: raise the appeal fee invoice (status Pending Appeal); a fee exempt appeal goes straight to review. */
    public function openAppeal(object $application): object
    {
        DB::table('applications')->where('crm_id', $application->crm_id)->update([
            'workflow_stage' => 'appeal_payment',
            'status' => 'Pending Appeal',
        ]);

        $institution = $this->institution($application);
        $invoice = $this->createQuotedInvoice($application, self::FEE_APPEAL, $this->appealFeeQuote($institution), (string) $application->appeal_deadline);
        if ($this->settleIfNothingDue($invoice, $institution)) {
            return DB::table('invoices')->where('crm_id', $invoice->crm_id)->first();
        }

        return $invoice;
    }

    /** Appeal upheld: the decline stands (status Declined). */
    public function upholdAppeal(object $application): void
    {
        DB::table('applications')->where('crm_id', $application->crm_id)->update([
            'workflow_stage' => 'end',
            'status' => 'Declined',
            'appeal_decision' => 'Upheld',
            'appeal_decision_date' => $this->today(),
            'appeal_successful' => false,
        ]);
    }

    /** Appeal overturned: the application returns to the approval decision (compliance order or not). */
    public function overturnAppeal(object $application): void
    {
        DB::table('applications')->where('crm_id', $application->crm_id)->update([
            'workflow_stage' => 'application_approval',
            'status' => 'Under Review',
            'appeal_decision' => 'Overturned',
            'appeal_decision_date' => $this->today(),
            'appeal_successful' => true,
        ]);
    }

    /**
     * Expire applications whose deadline passed: unpaid admin fee, unpaid appeal fee (the original
     * decline stands). An unanswered change request becomes Pending Admin Confirm Expiry instead.
     * Finally, Designated institutions whose designation lapsed without an approved renewal are
     * De-Designated (after the admin-fee expiry, so an unpaid approval no longer carries them).
     *
     * @return array{admin_fee: int, changes: int, appeal: int, lapsed: int}
     */
    public function enforceDeadlines(): array
    {
        $today = $this->today();
        $counts = ['admin_fee' => 0, 'changes' => 0, 'appeal' => 0, 'lapsed' => 0];

        foreach ([['admin_fee', self::FEE_ADMIN_VARIABLE], ['appeal_payment', self::FEE_APPEAL]] as [$stage, $feeType]) {
            $overdue = DB::table('applications as a')
                ->join('invoices as i', 'i.application_crm_id', '=', 'a.crm_id')
                ->where('a.workflow_stage', $stage)
                ->where('i.fee_type', $feeType)
                ->where('i.invoice_status', '<>', 'Paid')
                ->where('i.due_date', '<', $today)
                ->select('a.crm_id as application_crm_id', 'i.crm_id as invoice_crm_id')
                ->get();

            foreach ($overdue as $row) {
                DB::table('invoices')->where('crm_id', $row->invoice_crm_id)->update(['invoice_status' => 'Expired']);
                DB::table('applications')->where('crm_id', $row->application_crm_id)->update(
                    $stage === 'admin_fee'
                        ? ['workflow_stage' => 'end', 'status' => 'Expired']
                        : ['workflow_stage' => 'end', 'status' => 'Not Approved']
                );
                $counts[$stage === 'admin_fee' ? 'admin_fee' : 'appeal']++;
            }
        }

        // Missed resubmission deadline (each application's own changes_due_date): the ministry
        // confirms the expiry or extends the deadline, so it is not expired automatically.
        $counts['changes'] = DB::table('applications')
            ->where('workflow_stage', 'changes_requested')
            ->where('changes_due_date', '<', $today)
            ->update(['workflow_stage' => 'pending_expiry', 'status' => self::STATUS_PENDING_EXPIRY]);

        $counts['lapsed'] = $this->designation->deDesignateLapsed();

        return $counts;
    }

    private function completeDesignation(object $application): void
    {
        DB::table('applications')->where('crm_id', $application->crm_id)->update([
            'workflow_stage' => 'end',
            'process_completed' => true,
        ]);

        $approvedDate = (string) ($application->approved_date ?: $this->today());
        $this->designation->onApplicationApproved($application, $approvedDate, $application->designation_expiry);

        $institution = $this->institution($application);
        if ($institution !== null && ! $this->decidedOnAppeal($application)) {
            $this->notifier->applicationApproved($institution, (string) $application->reference, $approvedDate, [
                'designation_expiry' => (string) ($application->designation_expiry ?? ''),
            ]);
        }
    }

    /** Approved after an overturned appeal: the ADM sends the decision as a formal letter, so no decision emails. */
    private function decidedOnAppeal(object $application): bool
    {
        return ($application->appeal_decision ?? null) === 'Overturned';
    }

    /** Email the institution its payment receipt. Never lets a mail/PDF problem undo the payment. */
    private function sendReceipt(object $invoice, object $application): void
    {
        $institution = $this->institution($application);
        // A $0 invoice (no charge / fee exempt) involved no payment, so there is nothing to acknowledge.
        if ($institution === null || (float) ($invoice->total_charges ?? 0) <= 0) {
            return;
        }

        try {
            $this->notifier->paymentReceipt($institution, $invoice, $application);
        } catch (Throwable $e) {
            Log::warning('Payment receipt email failed for invoice '.($invoice->invoice_number ?? $invoice->crm_id).': '.$e->getMessage());
        }
    }

    private function institution(object $application): ?object
    {
        return empty($application->institution_crm_id)
            ? null
            : DB::table('institutions')->where('crm_id', $application->institution_crm_id)->first();
    }

    private function today(): string
    {
        return Carbon::now('America/Vancouver')->toDateString();
    }

    private function truthy(mixed $value): bool
    {
        return $value === true || $value === 't' || $value === 1 || $value === '1';
    }
}
