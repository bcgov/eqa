<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use App\Services\InvoicePdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Central dispatcher that reproduces the legacy Dynamics CRM notification
 * workflows/plugins in the migrated app. Each business event (institution/campus/
 * DBA create + change, application submission, decision, portal user creation,
 * and the time-based renewal / PTIB certificate expiry reminders) maps to one of
 * the migrated email_templates. Every send is funnelled through
 * EmailNotificationService, so the global master switch (default OFF) and the
 * testing-email redirect still govern whether anything actually goes out.
 */
class ProcessNotifier
{
    public function __construct(private EmailNotificationService $emails) {}

    // ---------------------------------------------------------------------
    // Change notifications (to the EQA mailbox) — legacy *NotificationEmail
    // plugins: "Notification of Change to Key Data Fields".
    // ---------------------------------------------------------------------

    /** @param array<string, mixed> $after */
    public function institutionChanged(object $before, array $after, ?string $modifiedBy = null): void
    {
        $changes = $this->diffRows($before, $after, [
            'name' => 'Name',
            'legal_name' => 'Legal Name',
            'bc_incorporation_number' => 'BC Incorporation Number',
            'qa_met_through' => 'QA Met Through',
            'website' => 'Web Url',
            'street1' => 'Address',
            'street2' => 'Address',
            'city' => 'City',
            'province' => 'Province',
            'country' => 'Country',
            'postal_code' => 'Postal Code',
            'total_enrolment' => 'Student Enrolment Size',
        ]);

        if ($changes === '') {
            return;
        }

        $this->emails->send('institution_change_notification', [
            'name' => (string) ($before->name ?? ''),
            'id' => (string) ($before->crm_id ?? ''),
            'modified_by' => $this->modifiedBy($modifiedBy),
            'modified_on' => now()->toDayDateTimeString(),
            'changes' => $changes,
        ], null, ['institution_crm_id' => $before->crm_id ?? null]);
    }

    /** @param array<string, mixed> $after */
    public function campusChanged(object $before, array $after, ?string $modifiedBy = null): void
    {
        $changes = $this->diffRows($before, $after, $this->locationFields());

        if ($changes === '') {
            return;
        }

        $this->emails->send('campus_change_notification', [
            'name' => (string) ($before->name ?? $before->location_name ?? ''),
            'id' => (string) ($before->crm_id ?? ''),
            'modified_by' => $this->modifiedBy($modifiedBy),
            'modified_on' => now()->toDayDateTimeString(),
            'changes' => $changes,
        ], null, ['institution_crm_id' => $before->institution_crm_id ?? null]);
    }

    /** @param array<string, mixed> $after */
    public function dbaChanged(object $before, array $after, ?string $modifiedBy = null): void
    {
        $changes = $this->diffRows($before, $after, $this->locationFields());

        if ($changes === '') {
            return;
        }

        $this->emails->send('dba_change_notification', [
            'name' => (string) ($before->name ?? ''),
            'id' => (string) ($before->crm_id ?? ''),
            'modified_by' => $this->modifiedBy($modifiedBy),
            'modified_on' => now()->toDayDateTimeString(),
            'changes' => $changes,
        ], null, ['institution_crm_id' => $before->institution_crm_id ?? null]);
    }

    // ---------------------------------------------------------------------
    // Creation notifications (to the EQA mailbox) — "New Campus/DBA created".
    // ---------------------------------------------------------------------

    /** @param array<string, mixed> $campus */
    public function campusCreated(array $campus, string $institutionName, ?string $institutionCrmId = null, ?string $modifiedBy = null): void
    {
        $this->emails->send('campus_created', [
            'institution_name' => $institutionName,
            'modified_by' => $this->modifiedBy($modifiedBy),
            'modified_on' => now()->toDayDateTimeString(),
            'primary_location' => ! empty($campus['primary_location']) ? 'Yes' : 'No',
            'email' => (string) ($campus['email'] ?? ''),
            'website' => (string) ($campus['website'] ?? ''),
            'street1' => (string) ($campus['street1'] ?? ''),
            'street2' => (string) ($campus['street2'] ?? ''),
            'street3' => (string) ($campus['street3'] ?? ''),
            'city' => (string) ($campus['city'] ?? ''),
            'province' => (string) ($campus['province'] ?? ''),
            'postal_code' => (string) ($campus['postal_code'] ?? ''),
            'country' => (string) ($campus['country'] ?? ''),
            'description' => (string) ($campus['description'] ?? ''),
        ], null, ['institution_crm_id' => $institutionCrmId]);
    }

    /** @param array<string, mixed> $dba */
    public function dbaCreated(array $dba, string $institutionName, ?string $institutionCrmId = null, ?string $modifiedBy = null): void
    {
        $this->emails->send('dba_created', [
            'institution_name' => $institutionName,
            'modified_by' => $this->modifiedBy($modifiedBy),
            'modified_on' => now()->toDayDateTimeString(),
            'dba_name' => (string) ($dba['name'] ?? ''),
            'email' => (string) ($dba['email'] ?? ''),
            'website' => (string) ($dba['website'] ?? ''),
            'street1' => (string) ($dba['street1'] ?? ''),
            'street2' => (string) ($dba['street2'] ?? ''),
            'street3' => (string) ($dba['street3'] ?? ''),
            'city' => (string) ($dba['city'] ?? ''),
            'province' => (string) ($dba['province'] ?? ''),
            'postal_code' => (string) ($dba['postal_code'] ?? ''),
            'country' => (string) ($dba['country'] ?? ''),
            'description' => (string) ($dba['description'] ?? ''),
        ], null, ['institution_crm_id' => $institutionCrmId]);
    }

    // ---------------------------------------------------------------------
    // Application submission confirmation (to the institution contact) —
    // "Application Submission Confirmation" workflow (new vs reapplication).
    // ---------------------------------------------------------------------

    public function applicationSubmitted(object $institution, string $reference, bool $isReapplication): void
    {
        $contact = $this->contact($institution);
        $vars = [
            'contact_name' => $contact['name'] !== '' ? $contact['name'] : 'Applicant',
            'institution_name' => (string) ($institution->name ?? ''),
        ];

        if ($isReapplication) {
            $vars['order_receipt'] = $reference;
            $this->emails->send('reapplication_submission', $vars, $contact['email'] ?: null, ['institution_crm_id' => $institution->crm_id ?? null]);
        } else {
            $vars['order_reference'] = $reference;
            $this->emails->send('application_submission', $vars, $contact['email'] ?: null, ['institution_crm_id' => $institution->crm_id ?? null]);
        }
    }

    /**
     * A given institution is "reapplying" if it has previously been designated
     * (has a designation start / designated status) or already has an approved
     * application on file; otherwise the submission is a first-time application.
     * A De-Designated status alone is not enough: it only counts when the record
     * shows a past designation (expiry or de-designation date), so an institution
     * that was never designated is charged the new application fee.
     */
    public function isReapplication(object $institution): bool
    {
        if (! empty($institution->designation_start)) {
            return true;
        }

        $status = trim((string) ($institution->eqa_status ?? ''));
        if (strcasecmp($status, 'Designated') === 0) {
            return true;
        }
        if (strcasecmp($status, 'De-Designated') === 0
            && (! empty($institution->de_designated_date ?? null) || ! empty($institution->designation_expiry))) {
            return true;
        }

        return Schema::hasTable('applications')
            && DB::table('applications')
                ->where('institution_crm_id', $institution->crm_id ?? null)
                ->where(function ($q): void {
                    $q->where('status', 'Approved')->orWhereNotNull('approved_date');
                })
                ->exists();
    }

    // ---------------------------------------------------------------------
    // Application approved (to the institution contact) — "Application Approval
    // Notification" workflow. Declines and appeal outcomes are not emailed: the
    // institution receives a formal letter outside the system.
    // ---------------------------------------------------------------------

    /** @param array<string, mixed> $extra */
    public function applicationApproved(object $institution, string $reference, string $today, array $extra = []): void
    {
        $contact = $this->contact($institution);

        $this->emails->send('application_approved', [
            'contact_name' => $contact['name'] !== '' ? $contact['name'] : 'Applicant',
            'institution_name' => (string) ($institution->name ?? ''),
            'application_reference' => $reference,
            'approved_date' => $today,
            'designation_expiry' => (string) ($extra['designation_expiry'] ?? ''),
        ], $contact['email'] ?: null, ['institution_crm_id' => $institution->crm_id ?? null]);
    }

    // ---------------------------------------------------------------------
    // Payment / appeal workflow (flowchart DRAFT V1.1).
    // ---------------------------------------------------------------------

    /** "Send email to Inst. for missing information" when the ministry requests additional information. */
    public function changesRequested(object $institution, object $application, string $changes, string $dueDate): void
    {
        $contact = $this->contact($institution);
        $this->emails->send('application_changes_requested', [
            'contact_name' => $contact['name'] !== '' ? $contact['name'] : 'Applicant',
            'institution_name' => (string) ($institution->name ?? ''),
            'application_reference' => (string) ($application->reference ?? ''),
            'changes_requested' => $changes,
            'changes_due_date' => $dueDate,
        ], $contact['email'] ?: null, ['institution_crm_id' => $institution->crm_id ?? null]);
    }

    /** Approval decision with the admin variable fee invoice (designation follows payment). */
    public function approvedFeeDue(object $institution, object $application, object $invoice, bool $complianceOrder, string $complianceNotes): void
    {
        $contact = $this->contact($institution);
        $this->emails->send('application_approved_fee_due', [
            'contact_name' => $contact['name'] !== '' ? $contact['name'] : 'Applicant',
            'institution_name' => (string) ($institution->name ?? ''),
            'application_reference' => (string) ($application->reference ?? ''),
            'approved_date' => (string) ($application->approved_date ?? ''),
            'compliance_order' => $complianceOrder
                ? 'This approval is issued under a compliance order: '.($complianceNotes !== '' ? $complianceNotes : 'see the EQA portal for details.')
                : '',
            'invoice_number' => (string) ($invoice->invoice_number ?? ''),
            'admin_fee' => '$'.number_format((float) ($invoice->total_charges ?? 0), 2),
            'invoice_description' => (string) ($invoice->description ?? ''),
            'due_date' => (string) ($invoice->due_date ?? ''),
        ], $contact['email'] ?: null, ['institution_crm_id' => $institution->crm_id ?? null]);
    }

    /**
     * "Payment Receipt": acknowledges a paid invoice to the institution with the receipt PDF
     * attached. Sent once per invoice; zero-amount invoices (nothing was paid) are skipped.
     */
    public function paymentReceipt(object $institution, object $invoice, ?object $application = null): void
    {
        $total = (float) ($invoice->total_charges ?? 0);
        $number = (string) ($invoice->invoice_number ?? '');
        if ($total <= 0 || $number === '' || ($invoice->invoice_status ?? null) !== 'Paid') {
            return;
        }

        $alreadySent = Schema::hasTable('sent_emails') && DB::table('sent_emails')
            ->where('template_key', 'payment_receipt')
            ->where('status', 'sent')
            ->where('body', 'like', '%'.$number.'%')
            ->exists();
        if ($alreadySent) {
            return;
        }

        $pdf = app(InvoicePdf::class);
        $contact = $this->contact($institution);
        $paid = (float) ($invoice->payment_amount ?? 0) > 0 ? (float) $invoice->payment_amount : $total;

        $this->emails->send('payment_receipt', [
            'contact_name' => $contact['name'] !== '' ? $contact['name'] : 'Applicant',
            'institution_name' => (string) ($institution->name ?? $invoice->institution_name ?? ''),
            'invoice_number' => $number,
            'fee_description' => $pdf->feeLabel($invoice),
            'amount_paid' => '$'.number_format($paid, 2),
            'payment_date' => (string) ($invoice->payment_received_date ?? ''),
            'payment_method' => (string) ($invoice->payment_method ?? ''),
            'transaction_reference' => (string) (($invoice->transaction_reference ?? null) ?: '—'),
            'application_reference' => (string) ($invoice->application_reference ?? $application->reference ?? ''),
        ], $contact['email'] ?: null, ['institution_crm_id' => $institution->crm_id ?? null], [[
            'data' => $pdf->render($invoice, true),
            'name' => $pdf->filename($invoice, true),
            'mime' => 'application/pdf',
        ]]);
    }

    /** "Send appeal application confirmation and payment receipt email". */
    public function appealSubmitted(object $institution, object $application, object $invoice): void
    {
        $contact = $this->contact($institution);
        $this->emails->send('appeal_submission', [
            'contact_name' => $contact['name'] !== '' ? $contact['name'] : 'Applicant',
            'institution_name' => (string) ($institution->name ?? ''),
            'application_reference' => (string) ($application->reference ?? ''),
            'invoice_number' => (string) ($invoice->invoice_number ?? ''),
            'amount_paid' => '$'.number_format((float) ($invoice->payment_amount ?? $invoice->total_charges ?? 0), 2),
            'transaction_reference' => (string) ($invoice->transaction_reference ?? ''),
            'appeal_date' => (string) ($application->appeal_date ?? ''),
        ], $contact['email'] ?: null, ['institution_crm_id' => $institution->crm_id ?? null]);
    }

    // ---------------------------------------------------------------------
    // Portal user creation — "Send Authorization Email" (welcome) + "New Portal
    // Administrator" notification.
    // ---------------------------------------------------------------------

    /** @param array<string, mixed> $user */
    public function portalUserCreated(array $user, object $institution): void
    {
        $email = (string) ($user['email'] ?? '');
        $name = (string) ($user['full_name'] ?? trim(((string) ($user['first_name'] ?? '')).' '.((string) ($user['last_name'] ?? ''))));
        $meta = ['institution_crm_id' => $institution->crm_id ?? null];

        // Welcome / authorization email — only when the contact is granted portal
        // access and we have somewhere to send it.
        if (! empty($user['web_user_active']) && $email !== '') {
            $this->emails->send('portal_welcome', [
                'contact_name' => $name !== '' ? $name : 'User',
            ], $email, $meta);
        }

        // Notify the ministry mailbox that a new portal administrator was added.
        if (stripos((string) ($user['role'] ?? ''), 'admin') !== false) {
            $this->emails->send('portal_new_admin', [
                'institution_name' => (string) ($institution->name ?? ''),
                'first_name' => (string) ($user['first_name'] ?? ''),
                'last_name' => (string) ($user['last_name'] ?? ''),
            ], null, $meta);
        }
    }

    // ---------------------------------------------------------------------
    // Time-based reminders — legacy scheduled workflows. Driven by the
    // eqa:send-notifications artisan command.
    // ---------------------------------------------------------------------

    /**
     * "EQA Reapplication Required" renewal reminders: designated institutions
     * whose designation expiry lands exactly on today + one of the offsets.
     *
     * @param  array<int, int>  $dayOffsets
     */
    public function sendRenewalReminders(array $dayOffsets = [90, 30]): int
    {
        if (! Schema::hasTable('institutions')) {
            return 0;
        }

        $sent = 0;
        foreach ($dayOffsets as $offset) {
            $target = Carbon::now()->addDays($offset)->toDateString();

            $institutions = DB::table('institutions')
                ->whereDate('designation_expiry', $target)
                ->whereRaw("COALESCE(eqa_status, '') ILIKE '%designat%'")
                ->get();

            foreach ($institutions as $institution) {
                $contact = $this->contact($institution);
                $this->emails->send('reapplication_required', [
                    'contact_name' => $contact['name'] !== '' ? $contact['name'] : 'Applicant',
                    'institution_name' => (string) ($institution->name ?? ''),
                    'designation_expiry' => (string) ($institution->designation_expiry ?? ''),
                ], $contact['email'] ?: null, ['institution_crm_id' => $institution->crm_id ?? null]);
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * "PTIB Certificate Expiry" reminders: Private Training pathway institutions
     * whose PTIB certificate expiry lands exactly on today + one of the offsets.
     *
     * @param  array<int, int>  $dayOffsets
     */
    public function sendPtibCertExpiryReminders(array $dayOffsets = [90, 30]): int
    {
        if (! Schema::hasTable('institutions') || ! Schema::hasColumn('institutions', 'ptib_cert_expiry')) {
            return 0;
        }

        $sent = 0;
        foreach ($dayOffsets as $offset) {
            $target = Carbon::now()->addDays($offset)->toDateString();

            $institutions = DB::table('institutions')
                ->whereDate('ptib_cert_expiry', $target)
                ->whereRaw("(COALESCE(qa_met_through, '') ILIKE '%(PTIB)%' OR COALESCE(qa_met_through, '') ILIKE '%(PTIRU)%')")
                ->get();

            foreach ($institutions as $institution) {
                $contact = $this->contact($institution);
                $this->emails->send('ptib_cert_expiry', [
                    'institution_name' => (string) ($institution->name ?? ''),
                    'expiry_date' => (string) ($institution->ptib_cert_expiry ?? ''),
                ], $contact['email'] ?: null, ['institution_crm_id' => $institution->crm_id ?? null]);
                $sent++;
            }
        }

        return $sent;
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Resolve the institution's contact — name + email. The primary contact email recorded by the
     * ministry on the institution profile wins; otherwise the best portal contact: an active
     * administrator with an email, then any active portal user, then any user with an email.
     * The name is the institution's primary_contact when set.
     *
     * @return array{name: string, email: string}
     */
    private function contact(?object $institution): array
    {
        $name = (string) ($institution->primary_contact ?? '');
        $email = trim((string) ($institution->primary_contact_email ?? ''));
        $crmId = $institution->crm_id ?? null;

        if ($email !== '') {
            return ['name' => trim($name), 'email' => $email];
        }

        if ($crmId && Schema::hasTable('institution_users')) {
            $user = DB::table('institution_users')
                ->where('institution_crm_id', $crmId)
                ->whereNotNull('email')
                ->where('email', '<>', '')
                ->orderByRaw('CASE WHEN web_user_active THEN 0 ELSE 1 END')
                ->orderByRaw("CASE WHEN LOWER(COALESCE(role, '')) LIKE '%admin%' THEN 0 ELSE 1 END")
                ->first();

            if ($user !== null) {
                $email = (string) ($user->email ?? '');
                if ($name === '') {
                    $name = (string) ($user->full_name ?? trim(((string) ($user->first_name ?? '')).' '.((string) ($user->last_name ?? ''))));
                }
            }
        }

        return ['name' => trim($name), 'email' => trim($email)];
    }

    /**
     * Shared key-field list for campus + DBA change notifications.
     *
     * @return array<string, string>
     */
    private function locationFields(): array
    {
        return [
            'name' => 'Name',
            'email' => 'Email',
            'website' => 'Web Url',
            'street1' => 'Address',
            'street2' => 'Address',
            'street3' => 'Address',
            'city' => 'City',
            'province' => 'Province',
            'postal_code' => 'Postal Code',
            'country' => 'Country',
            'description' => 'Description',
        ];
    }

    /**
     * Build the "<field> — Old: … → New: …" HTML block for changed fields only.
     *
     * @param  array<string, mixed>  $after
     * @param  array<string, string>  $fields
     */
    private function diffRows(object $before, array $after, array $fields): string
    {
        $rows = [];
        foreach ($fields as $field => $label) {
            if (! array_key_exists($field, $after)) {
                continue;
            }
            $old = (string) ($before->{$field} ?? '');
            $new = (string) ($after[$field] ?? '');
            if ($old !== $new) {
                $rows[] = '<p><b>'.$label.'</b> &mdash; Old: '.e($old !== '' ? $old : '(empty)').' &rarr; New: '.e($new !== '' ? $new : '(empty)').'</p>';
            }
        }

        return implode('', $rows);
    }

    private function modifiedBy(?string $modifiedBy): string
    {
        $modifiedBy = trim((string) $modifiedBy);

        return $modifiedBy !== '' ? $modifiedBy : 'System';
    }
}
