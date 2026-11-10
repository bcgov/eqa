<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Notification templates for the payment / appeal workflow (flowchart DRAFT V1.1).
// Upsert by key; preserves an admin's active flag on re-run.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        foreach ($this->templates() as $tpl) {
            $payload = array_merge($tpl, ['updated_at' => now()]);

            if (DB::table('email_templates')->where('key', $tpl['key'])->exists()) {
                DB::table('email_templates')->where('key', $tpl['key'])->update($payload);
            } else {
                DB::table('email_templates')->insert(array_merge($payload, ['is_active' => true, 'created_at' => now()]));
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('email_templates')) {
            DB::table('email_templates')->whereIn('key', array_column($this->templates(), 'key'))->delete();
        }
    }

    /** @return array<int, array<string, string>> */
    private function templates(): array
    {
        $eqa = 'EQA@gov.bc.ca';
        $signature = '<p>Sincerely,</p><p><b><i>Education Quality Assurance</i></b><br>'.
            'Email: <a href="mailto:EQA@gov.bc.ca">EQA@gov.bc.ca</a></p>';

        return [
            [
                'key' => 'application_changes_requested',
                'name' => 'Application — Additional Information Required',
                'category' => 'Application Review',
                'subject' => 'EQA Application {{application_reference}} | Additional Information Required',
                'recipients' => $eqa,
                'description' => 'Sent when the ministry reopens an incomplete application for changes (status Draft).',
                'variables' => 'contact_name, institution_name, application_reference, changes_requested, changes_due_date',
                'body' => '<p>Dear {{contact_name}}</p>'.
                    '<p>The EQA application {{application_reference}} for {{institution_name}} is missing information and has been returned to you for changes.</p>'.
                    '<p><b>Information required:</b><br>{{changes_requested}}</p>'.
                    '<p>Please sign in to the EQA portal, update the application and resubmit it by <b>{{changes_due_date}}</b>. Applications not resubmitted by this date will expire.</p>'.
                    $signature,
            ],
            [
                'key' => 'application_approved_fee_due',
                'name' => 'Application — Approved, Administrative Fee Due',
                'category' => 'Application Decision',
                'subject' => 'EQA Application {{application_reference}} Approved | Administrative Fee Due',
                'recipients' => $eqa,
                'description' => 'Decision email sent on approval with the admin variable fee invoice. Designation is granted once the fee is paid.',
                'variables' => 'contact_name, institution_name, application_reference, approved_date, compliance_order, invoice_number, admin_fee, due_date',
                'body' => '<p>Dear {{contact_name}}</p>'.
                    '<p>The EQA application {{application_reference}} for {{institution_name}} was approved on {{approved_date}}.</p>'.
                    '<p>{{compliance_order}}</p>'.
                    '<p>To complete your designation, please pay the administrative fee of <b>{{admin_fee}}</b> (invoice {{invoice_number}}) through the EQA portal by <b>{{due_date}}</b>. If the fee is not paid by this date the application will expire.</p>'.
                    $signature,
            ],
            [
                'key' => 'appeal_submission',
                'name' => 'Appeal — Confirmation and Payment Receipt',
                'category' => 'Appeal',
                'subject' => 'EQA Appeal Received | {{application_reference}}',
                'recipients' => $eqa,
                'description' => 'Confirms an appeal of a declined application once the appeal fee is paid.',
                'variables' => 'contact_name, institution_name, application_reference, invoice_number, amount_paid, transaction_reference, appeal_date',
                'body' => '<p>Dear {{contact_name}}</p>'.
                    '<p>We have received the appeal of EQA application {{application_reference}} for {{institution_name}} on {{appeal_date}}. The ministry will review the appeal and notify you of the decision.</p>'.
                    '<p><b>Payment receipt</b><br>Invoice: {{invoice_number}}<br>Amount paid: {{amount_paid}}<br>Transaction reference: {{transaction_reference}}</p>'.
                    $signature,
            ],
        ];
    }
};
