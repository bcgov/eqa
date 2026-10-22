<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
    public function applicationFeeQuote(string $feeType): array
    {
        $label = $feeType === self::FEE_APPLICATION_RENEWAL ? 'Renewal Application Fee' : 'New Application Fee';

        return $this->quote([$this->line($feeType, $label.' (fixed)', 1, (float) config('eqa.fees.'.$feeType))]);
    }

    /** @return array{amount: float, description: string, line_items: list<array<string, mixed>>} */
    public function appealFeeQuote(): array
    {
        return $this->quote([$this->line(self::FEE_APPEAL, 'Appeal Fee (fixed)', 1, (float) config('eqa.fees.appeal'))]);
    }

    /**
     * Administrative fee: multiple location fee (private institutions with more than one active
     * location) plus a per international student fee (study permit + other, from the application).
     *
     * @return array{amount: float, description: string, line_items: list<array<string, mixed>>}
     */
    public function adminFeeQuote(object $application): array
    {
        $institution = $this->institution($application);
        $qaMetThrough = trim((string) ($institution->qa_met_through ?? ''));
        $private = $qaMetThrough !== self::PUBLIC_QA_MET_THROUGH;
        $locations = $institution !== null
            ? DB::table('campuses')->where('institution_crm_id', $institution->crm_id)->where('status', 'Active')->count()
            : 0;
        $permit = (int) ($application->intl_students_permit ?? 0);
        $other = (int) ($application->intl_students_other ?? 0);
        $locationText = $locations.' active location'.($locations === 1 ? '' : 's');

        $lines = [];
        $notes = [];
        if ($private && $locations > 1) {
            $lines[] = $this->line('multi_location', 'Multiple Location Fee (private institution, '.$locationText.')', 1, (float) config('eqa.fees.multi_location'));
        } else {
            $notes[] = 'Multiple Location Fee not applied ('.($private ? 'private institution, '.$locationText : 'public institution').')';
        }
        if ($permit + $other > 0) {
            $lines[] = $this->line('international_students', "International Student Fee ({$permit} study permit + {$other} other)", $permit + $other, (float) config('eqa.fees.per_international_student'));
        } else {
            $notes[] = 'International Student Fee not applied (0 international students)';
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

    /** Ministry "Confirm Payment" for a payment made outside Express Pay (cheque, EFT, ...). */
    public function recordManualPayment(object $invoice, string $method = 'Manual'): void
    {
        if (($invoice->invoice_status ?? null) !== 'Paid') {
            $due = (float) ($invoice->invoice_balance ?? $invoice->total_charges ?? 0);
            DB::table('invoices')->where('crm_id', $invoice->crm_id)->update([
                'invoice_balance' => 0,
                'invoice_status' => 'Paid',
                'payment_amount' => round((float) ($invoice->payment_amount ?? 0) + $due, 2),
                'payment_received_date' => $this->today(),
                'payment_method' => $method,
            ]);
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

        switch ($invoice->fee_type ?? null) {
            case self::FEE_APPLICATION_NEW:
            case self::FEE_APPLICATION_RENEWAL:
                DB::table('applications')->where('crm_id', $application->crm_id)->update(['fees_payment_received' => true]);
                if ($application->workflow_stage === 'draft' && ! $this->truthy($application->need_additional_details)) {
                    $this->submit($application);
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

    /** Draft -> Pending Review; a reopened draft goes straight back to Under Review. */
    public function submit(object $application): void
    {
        if ($this->truthy($application->need_additional_details)) {
            DB::table('applications')->where('crm_id', $application->crm_id)->update([
                'workflow_stage' => 'under_review',
                'status' => 'Under Review',
                'need_additional_details' => false,
                'resubmission_date' => $this->today(),
            ]);

            return;
        }

        DB::table('applications')->where('crm_id', $application->crm_id)->update([
            'workflow_stage' => 'pending_review',
            'status' => 'Pending Review',
        ]);

        $institution = $this->institution($application);
        $feeInvoice = $this->invoiceFor($application->crm_id, self::APPLICATION_FEES)
            ?? $this->createApplicationFeeInvoice($application, $institution);
        if ($institution !== null) {
            $this->notifier->applicationSubmitted($institution, (string) $application->reference, $feeInvoice->fee_type === self::FEE_APPLICATION_RENEWAL);
        }
    }

    /** A draft that has never been submitted: no fees are requested until it is. */
    public function awaitingSubmission(object $application): bool
    {
        return ($application->workflow_stage ?? null) === 'draft' && ! $this->truthy($application->need_additional_details ?? null);
    }

    private function createApplicationFeeInvoice(object $application, ?object $institution): object
    {
        $feeType = $institution !== null && $this->notifier->isReapplication($institution)
            ? self::FEE_APPLICATION_RENEWAL
            : self::FEE_APPLICATION_NEW;
        $quote = $this->applicationFeeQuote($feeType);

        DB::table('applications')->where('crm_id', $application->crm_id)->update([
            'application_fee' => $quote['amount'],
            'total_due' => $quote['amount'],
        ]);

        return $this->createQuotedInvoice($application, $feeType, $quote);
    }

    /** Ministry reopens an incomplete application so the institution can supply missing information. */
    public function requestChanges(object $application, string $changes): void
    {
        $due = Carbon::now('America/Vancouver')->addDays((int) config('eqa.deadlines.changes_days', 30))->toDateString();

        DB::table('applications')->where('crm_id', $application->crm_id)->update([
            'workflow_stage' => 'draft',
            'status' => 'Draft',
            'need_additional_details' => true,
            'changes_requested' => $changes,
            'changes_due_date' => $due,
        ]);

        $institution = $this->institution($application);
        if ($institution !== null) {
            $this->notifier->changesRequested($institution, $application, $changes, $due);
        }
    }

    /** Approval (optionally under a compliance order): raise the administrative fee invoice and send the decision. */
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

        // Nothing to pay (e.g. public institution, no international students): designate now.
        if ($quote['amount'] <= 0) {
            $this->recordManualPayment($invoice, 'No Charge');

            return DB::table('invoices')->where('crm_id', $invoice->crm_id)->first();
        }

        $institution = $this->institution($application);
        if ($institution !== null) {
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

    /** Institution chooses to appeal: raise the appeal fee invoice (status Pending Appeal). */
    public function openAppeal(object $application): object
    {
        DB::table('applications')->where('crm_id', $application->crm_id)->update([
            'workflow_stage' => 'appeal_payment',
            'status' => 'Pending Appeal',
        ]);

        return $this->createQuotedInvoice($application, self::FEE_APPEAL, $this->appealFeeQuote(), (string) $application->appeal_deadline);
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
     * Expire applications whose deadline passed: unpaid admin fee, unanswered change request,
     * unpaid appeal fee (the original decline stands).
     *
     * @return array{admin_fee: int, changes: int, appeal: int}
     */
    public function enforceDeadlines(): array
    {
        $today = $this->today();
        $counts = ['admin_fee' => 0, 'changes' => 0, 'appeal' => 0];

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

        $counts['changes'] = DB::table('applications')
            ->where('workflow_stage', 'draft')
            ->where('need_additional_details', true)
            ->where('changes_due_date', '<', $today)
            ->update(['workflow_stage' => 'end', 'status' => 'Expired']);

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
        if ($institution !== null) {
            $this->notifier->applicationDecision(true, $institution, (string) $application->reference, $approvedDate, [
                'designation_expiry' => (string) ($application->designation_expiry ?? ''),
            ]);
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
