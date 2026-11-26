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

    /** Stages covered by the application completion deadline: started but the application fee not yet paid. */
    public const COMPLETION_STAGES = ['draft', 'fee_required'];

    private const FEE_LABELS = [
        self::FEE_APPLICATION_NEW => 'New Application Fee',
        self::FEE_APPLICATION_RENEWAL => 'Renewal Application Fee',
        self::FEE_ADMIN_VARIABLE => 'Administrative Fee',
        self::FEE_APPEAL => 'Appeal Fee',
    ];

    public const STATUS_FEE_REQUIRED = 'Application Fee Required';

    public const STATUS_FEE_RECEIVED = 'Fee Payment Received';

    /** Approve decision taken: the administrative fee is invoiced and the approval follows its confirmed payment. */
    public const STATUS_ADMIN_FEE_REQUIRED = 'Fee Required';

    public const STATUS_ADMIN_FEE_RECEIVED = 'Administrative Fee Received';

    /** Administrative fee confirmed: waiting for the approval decision (with or without a compliance order). */
    public const STATUS_PENDING_APPROVAL = 'Pending Approval';

    public const STATUS_DECLINED = 'Declined';

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
                    if (! empty($application->approved_date)) {
                        // Approved before the administrative fee moved ahead of the approval decision.
                        $this->completeDesignation($application);
                    } elseif ((float) ($invoice->total_charges ?? 0) <= 0) {
                        // Nothing was paid, so there is no payment for the ministry to confirm.
                        $this->adminFeeConfirmed($application);
                    } else {
                        DB::table('applications')->where('crm_id', $application->crm_id)->update([
                            'workflow_stage' => 'admin_fee_paid',
                            'status' => self::STATUS_ADMIN_FEE_RECEIVED,
                        ]);
                    }
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

        // The fee is due by the application completion deadline.
        return $this->createQuotedInvoice($application, $feeType, $quote, $this->ensureCompletionDueDate($application));
    }

    /** Application completion deadline for an application started on $startDate (default today): Maintenance > Utils. */
    public function completionDueDate(?string $startDate = null): string
    {
        $start = $startDate ? Carbon::parse($startDate, 'America/Vancouver') : Carbon::now('America/Vancouver');

        return $start->addDays((int) config('eqa.deadlines.application_days', 30))->toDateString();
    }

    /** Applications started before the deadline existed get a full period from today. */
    private function ensureCompletionDueDate(object $application): string
    {
        if (! empty($application->completion_due_date)) {
            return substr((string) $application->completion_due_date, 0, 10);
        }

        $due = $this->completionDueDate();
        DB::table('applications')->where('crm_id', $application->crm_id)->update(['completion_due_date' => $due]);

        return $due;
    }

    /**
     * Ministry moves the completion deadline of a draft or unpaid application. The unpaid application
     * fee invoice follows, and the reminder is sent again before the new date.
     */
    public function extendCompletionDeadline(object $application, string $dueDate): void
    {
        DB::table('applications')->where('crm_id', $application->crm_id)->update([
            'completion_due_date' => $dueDate,
            'completion_reminder_sent_at' => null,
        ]);

        DB::table('invoices')
            ->where('application_crm_id', $application->crm_id)
            ->whereIn('fee_type', self::APPLICATION_FEES)
            ->whereNotIn('invoice_status', ['Paid', 'Expired'])
            ->update(['due_date' => $dueDate]);
    }

    /** Time of day (America/Vancouver) the daily eqa:enforce-deadlines job runs (routes/console.php). */
    public const DEADLINE_JOB_TIME = '01:00';

    /** Application deadlines the ministry can set by hand on the review page. */
    public const DEADLINE_KEYS = ['completion', 'changes', 'admin_fee', 'appeal'];

    /**
     * Every deadline of the application for the ministry's review page: its date, whether it is
     * running at the current stage (only those can be changed), what happens when it passes and
     * when that is applied (the daily job runs the day after the due date at DEADLINE_JOB_TIME).
     *
     * @return list<array{key: string, label: string, date: ?string, active: bool, editable: bool, outcome: string, applies_at: ?string, applied_by: string, mode: string, note: ?string}>
     */
    public function deadlines(object $application): array
    {
        $crmId = (string) $application->crm_id;
        $stage = $application->workflow_stage ?: 'draft';
        $adminFee = $this->invoiceFor($crmId, self::FEE_ADMIN_VARIABLE);
        $institution = $this->institution($application);
        $job = 'Daily deadline job ('.self::DEADLINE_JOB_TIME.')';
        $date = fn ($value): ?string => empty($value) ? null : substr((string) $value, 0, 10);

        $completion = $date($application->completion_due_date ?? null);
        $reminderDays = (int) config('eqa.deadlines.application_reminder_days', 7);
        $reminder = ! empty($application->completion_reminder_sent_at)
            ? 'Reminder emailed '.substr((string) $application->completion_reminder_sent_at, 0, 10).'.'
            : ($completion ? 'Reminder email due '.Carbon::parse($completion)->subDays($reminderDays)->toDateString().' ('.$reminderDays.' days before).' : null);

        $appealPaying = $stage === 'appeal_payment';
        $appealOpen = $appealPaying || ($stage === 'end' && $application->status === self::STATUS_DECLINED && empty($application->appeal_decision));
        $appeal = $date($application->appeal_deadline ?? null);

        $designated = ($institution->eqa_status ?? null) === 'Designated';

        return [
            $this->deadline('completion', 'Application completion (submit and pay the application fee)', $completion,
                in_array($stage, self::COMPLETION_STAGES, true), 'The application expires (Expired).', $job, $reminder),
            $this->deadline('changes', 'Resubmission of additional information', $date($application->changes_due_date ?? null),
                $stage === 'changes_requested', 'Moves to Pending Admin Confirm Expiry for the ministry to confirm or extend.', $job),
            $this->deadline('admin_fee', 'Administrative fee payment', $date($adminFee->due_date ?? null),
                $stage === 'admin_fee', 'The application expires (Expired).', $job),
            $this->deadline('appeal', $appealPaying ? 'Appeal fee payment (appeal deadline)' : 'Appeal window', $appeal,
                $appealOpen, $appealPaying ? 'The appeal lapses and the decline stands (Declined).' : 'The Appeal Decision button is no longer shown.',
                $appealPaying ? $job : 'Immediately (checked when the institution opens the application)', null, $appealPaying ? 'job' : 'immediate'),
            array_merge($this->deadline('designation', 'Institution designation expiry', $date($institution->designation_expiry ?? null),
                $designated, 'No automatic change: the institution stays Designated until an application is declined or the ministry changes its EQA Status.',
                'Not enforced by a job', 'The institution should apply for renewal before this date. Renewal reminders are emailed 90 and 30 days before (07:00 notification job). Change this date on the institution page.', 'none'),
                ['editable' => false]),
        ];
    }

    /**
     * @param  'job'|'immediate'|'none'  $mode  how a passed deadline takes effect
     * @return array{key: string, label: string, date: ?string, active: bool, editable: bool, outcome: string, applies_at: ?string, applied_by: string, mode: string, note: ?string}
     */
    private function deadline(string $key, string $label, ?string $date, bool $active, string $outcome, string $appliedBy, ?string $note = null, string $mode = 'job'): array
    {
        $appliesAt = null;
        if ($date !== null) {
            // A deadline is met through the whole due date; the daily job acts on it the next day.
            $appliesAt = $mode === 'job'
                ? Carbon::parse($date.' '.self::DEADLINE_JOB_TIME, 'America/Vancouver')->addDay()->toIso8601String()
                : Carbon::parse($date, 'America/Vancouver')->addDay()->startOfDay()->toIso8601String();
        }

        return [
            'key' => $key,
            'label' => $label,
            'date' => $date,
            'active' => $active,
            'editable' => $active,
            'outcome' => $outcome,
            'applies_at' => $appliesAt,
            'applied_by' => $appliedBy,
            'mode' => $mode,
            'note' => $note,
        ];
    }

    /**
     * Ministry sets a running deadline by hand (no email), including a date already passed so the
     * outcome can be checked without waiting: the daily job (or Run Deadline Checks Now) applies it.
     */
    public function setDeadline(object $application, string $key, string $date): void
    {
        $crmId = (string) $application->crm_id;
        $unpaid = fn (string|array $feeTypes) => DB::table('invoices')
            ->where('application_crm_id', $crmId)
            ->whereIn('fee_type', (array) $feeTypes)
            ->whereNotIn('invoice_status', ['Paid', 'Expired']);

        match ($key) {
            'completion' => $this->extendCompletionDeadline($application, $date),
            'changes' => DB::table('applications')->where('crm_id', $crmId)->update(['changes_due_date' => $date]),
            'admin_fee' => $unpaid(self::FEE_ADMIN_VARIABLE)->update(['due_date' => $date]),
            'appeal' => [
                DB::table('applications')->where('crm_id', $crmId)->update(['appeal_deadline' => $date]),
                $unpaid(self::FEE_APPEAL)->update(['due_date' => $date]),
            ],
        };
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

    /**
     * Approve decision (eligibility met, suitability passed or appeal overturned): the administrative
     * fee is invoiced (status Fee Required). The approval itself, with or without a compliance order,
     * is recorded once the fee is paid and the ministry has confirmed the payment. The fee email is
     * not sent after an overturned appeal (the ADM sends a formal letter).
     */
    public function requireAdminFee(object $application): object
    {
        $due = Carbon::now('America/Vancouver')->addDays((int) config('eqa.deadlines.admin_fee_days', 30))->toDateString();
        $quote = $this->adminFeeQuote($application);

        DB::table('applications')->where('crm_id', $application->crm_id)->update([
            'workflow_stage' => 'admin_fee',
            'status' => self::STATUS_ADMIN_FEE_REQUIRED,
            'designation_expiry' => $application->designation_expiry ?: Carbon::now('America/Vancouver')->addYear()->toDateString(),
        ]);
        $application = DB::table('applications')->where('crm_id', $application->crm_id)->first();
        $institution = $this->institution($application);

        $invoice = $this->createQuotedInvoice($application, self::FEE_ADMIN_VARIABLE, $quote, $due);

        // Nothing to pay (e.g. public institution with no international students, or fee exempt):
        // straight to the approval decision.
        if ($this->settleIfNothingDue($invoice, $institution)) {
            return DB::table('invoices')->where('crm_id', $invoice->crm_id)->first();
        }

        if ($institution !== null && ! $this->decidedOnAppeal($application)) {
            $this->notifier->adminFeeRequired($institution, $application, $invoice);
        }

        return $invoice;
    }

    /** Ministry confirmed the administrative fee payment: the approval decision is next. */
    public function adminFeeConfirmed(object $application): void
    {
        DB::table('applications')->where('crm_id', $application->crm_id)->update([
            'workflow_stage' => 'application_approval',
            'status' => self::STATUS_PENDING_APPROVAL,
        ]);
    }

    /** The administrative fee of this application has been paid (only then can it be approved). */
    public function adminFeePaid(string $applicationCrmId): bool
    {
        return ($this->invoiceFor($applicationCrmId, self::FEE_ADMIN_VARIABLE)->invoice_status ?? null) === 'Paid';
    }

    /**
     * Approval (optionally under a compliance order), once the administrative fee is paid and
     * confirmed: the institution is Designated, its profile enrolment follows this application and
     * the decision is emailed (not after an overturned appeal).
     */
    public function approve(object $application, bool $complianceOrder, string $complianceNotes): void
    {
        $today = $this->today();
        DB::table('applications')->where('crm_id', $application->crm_id)->update([
            'workflow_stage' => 'end',
            'process_completed' => true,
            'status' => $complianceOrder ? 'Approved (Under Compliance Order)' : 'Approved',
            'designation_decision' => true,
            'approved_date' => $today,
            'designation_expiry' => $application->designation_expiry ?: Carbon::now('America/Vancouver')->addYear()->toDateString(),
            'compliance_order' => $complianceOrder,
            'compliance_order_notes' => $complianceOrder ? $complianceNotes : null,
        ]);
        $application = DB::table('applications')->where('crm_id', $application->crm_id)->first();

        $this->designation->syncEnrolmentFromApplication($application);
        $this->designation->onApplicationApproved($application, $today, $application->designation_expiry);

        $institution = $this->institution($application);
        if ($institution !== null && ! $this->decidedOnAppeal($application)) {
            $this->notifier->applicationApproved($institution, (string) $application->reference, $today, [
                'designation_expiry' => (string) ($application->designation_expiry ?? ''),
            ]);
        }
    }

    public function canAppeal(object $application): bool
    {
        return $application->status === self::STATUS_DECLINED
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
            'status' => self::STATUS_DECLINED,
            'appeal_decision' => 'Upheld',
            'appeal_decision_date' => $this->today(),
            'appeal_successful' => false,
        ]);
    }

    /** Appeal overturned: approved, so the administrative fee is invoiced (status Fee Required), without the fee email. */
    public function overturnAppeal(object $application): object
    {
        DB::table('applications')->where('crm_id', $application->crm_id)->update([
            'appeal_decision' => 'Overturned',
            'appeal_decision_date' => $this->today(),
            'appeal_successful' => true,
        ]);

        return $this->requireAdminFee(DB::table('applications')->where('crm_id', $application->crm_id)->first());
    }

    /**
     * Expire applications whose deadline passed: unpaid admin fee, unpaid appeal fee (the original
     * decline stands). An unanswered change request becomes Pending Admin Confirm Expiry instead.
     * Drafts and unpaid applications past their completion deadline expire, and those nearing it
     * are sent a reminder. (A designation expiry date does not de-designate the institution.)
     *
     * @return array{admin_fee: int, changes: int, appeal: int, incomplete: int, reminders: int}
     */
    public function enforceDeadlines(): array
    {
        $today = $this->today();
        $counts = ['admin_fee' => 0, 'changes' => 0, 'appeal' => 0, 'incomplete' => 0, 'reminders' => 0];

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
                        : ['workflow_stage' => 'end', 'status' => self::STATUS_DECLINED]
                );
                if ($stage === 'admin_fee') {
                    // Designated on approval but the administrative fee was never paid.
                    $this->designation->undoDesignationOnApproval(DB::table('applications')->where('crm_id', $row->application_crm_id)->first());
                }
                $counts[$stage === 'admin_fee' ? 'admin_fee' : 'appeal']++;
            }
        }

        // Missed resubmission deadline (each application's own changes_due_date): the ministry
        // confirms the expiry or extends the deadline, so it is not expired automatically.
        $counts['changes'] = DB::table('applications')
            ->where('workflow_stage', 'changes_requested')
            ->where('changes_due_date', '<', $today)
            ->update(['workflow_stage' => 'pending_expiry', 'status' => self::STATUS_PENDING_EXPIRY]);

        $counts['incomplete'] = $this->expireIncompleteApplications($today);
        $counts['reminders'] = $this->sendCompletionReminders($today);

        return $counts;
    }

    /**
     * Applications not submitted and paid by their completion deadline expire, along with their unpaid
     * application fee invoice. One whose online payment is still being confirmed is left alone.
     */
    private function expireIncompleteApplications(string $today): int
    {
        $expressPay = app(ExpressPayService::class);
        $overdue = DB::table('applications')
            ->whereIn('workflow_stage', self::COMPLETION_STAGES)
            ->whereNotNull('completion_due_date')
            ->where('completion_due_date', '<', $today)
            ->get();

        $expired = 0;
        foreach ($overdue as $application) {
            $invoice = $this->invoiceFor((string) $application->crm_id, self::APPLICATION_FEES);
            if ($invoice !== null && ($invoice->invoice_status === 'Paid' || $expressPay->hasPaymentUnderReview((string) $invoice->crm_id))) {
                continue;
            }
            if ($invoice !== null && $invoice->invoice_status !== 'Expired') {
                DB::table('invoices')->where('crm_id', $invoice->crm_id)->update(['invoice_status' => 'Expired']);
            }
            DB::table('applications')->where('crm_id', $application->crm_id)->update(['workflow_stage' => 'end', 'status' => 'Expired']);
            $expired++;
        }

        return $expired;
    }

    /** Email a reminder once to institutions whose draft or unpaid application is nearing its completion deadline. */
    private function sendCompletionReminders(string $today): int
    {
        $cutoff = Carbon::parse($today, 'America/Vancouver')->addDays((int) config('eqa.deadlines.application_reminder_days', 7))->toDateString();
        $due = DB::table('applications')
            ->whereIn('workflow_stage', self::COMPLETION_STAGES)
            ->whereNull('completion_reminder_sent_at')
            ->whereBetween('completion_due_date', [$today, $cutoff])
            ->get();

        $sent = 0;
        foreach ($due as $application) {
            $institution = $this->institution($application);
            if ($institution === null) {
                continue;
            }
            try {
                $this->notifier->completionReminder($institution, $application, $this->invoiceFor((string) $application->crm_id, self::APPLICATION_FEES));
            } catch (Throwable $e) {
                Log::warning('Completion reminder email failed for application '.($application->reference ?? $application->crm_id).': '.$e->getMessage());

                continue;
            }
            DB::table('applications')->where('crm_id', $application->crm_id)->update(['completion_reminder_sent_at' => Carbon::now()]);
            $sent++;
        }

        return $sent;
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
