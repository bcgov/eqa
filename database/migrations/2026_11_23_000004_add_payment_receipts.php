<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Payment receipts for institutions: the receipt wording (Maintenance > Invoice & Receipt)
// and the "Payment Receipt" email that carries the receipt PDF as an attachment.
return new class extends Migration
{
    private const UTIL_KEYS = ['invoice_receipt_title', 'invoice_receipt_message'];

    private const TEMPLATE_KEY = 'payment_receipt';

    public function up(): void
    {
        $now = Carbon::now();

        if (Schema::hasTable('utils')) {
            $rows = [
                ['invoice_receipt_title', 'Receipt Title', 'eqa.invoice.receipt_title', 'text', 'Large heading at the top right of the payment receipt.'],
                ['invoice_receipt_message', 'Receipt Message', 'eqa.invoice.receipt_message', 'multiline', 'Text in the box at the bottom of the payment receipt (under the Paid Heading).'],
            ];
            foreach ($rows as $order => [$key, $label, $path, $format, $description]) {
                if (DB::table('utils')->where('field_key', $key)->exists()) {
                    continue;
                }
                DB::table('utils')->insert([
                    'field_type' => 'Invoice',
                    'field_key' => $key,
                    'field_label' => $label,
                    'field_name' => (string) config($path),
                    'field_description' => $description,
                    'field_format' => $format,
                    'active_flag' => true,
                    'sort_order' => 120 + $order,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('email_templates') && ! DB::table('email_templates')->where('key', self::TEMPLATE_KEY)->exists()) {
            DB::table('email_templates')->insert([
                'key' => self::TEMPLATE_KEY,
                'name' => 'Payment — Receipt',
                'category' => 'Payment',
                'subject' => 'EQA Payment Received | Invoice {{invoice_number}}',
                'recipients' => 'EQA@gov.bc.ca',
                'description' => 'Sent to the institution when an invoice is paid (online, or recorded by the ministry). The payment receipt PDF is attached.',
                'variables' => 'contact_name, institution_name, invoice_number, fee_description, amount_paid, payment_date, payment_method, transaction_reference, application_reference',
                'body' => '<p>Dear {{contact_name}}</p>'.
                    '<p>Thank you. We have received your payment for {{institution_name}}.</p>'.
                    '<p><b>Payment details</b><br>'.
                    'Invoice: {{invoice_number}} ({{fee_description}})<br>'.
                    'Application: {{application_reference}}<br>'.
                    'Amount paid: {{amount_paid}}<br>'.
                    'Payment date: {{payment_date}}<br>'.
                    'Payment method: {{payment_method}}<br>'.
                    'Transaction reference: {{transaction_reference}}</p>'.
                    '<p>Your receipt is attached to this email. You can also download it at any time from the Invoices page of the EQA portal.</p>'.
                    '<p>Sincerely,</p><p><b><i>Education Quality Assurance</i></b><br>'.
                    'Email: <a href="mailto:EQA@gov.bc.ca">EQA@gov.bc.ca</a></p>',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('utils')) {
            DB::table('utils')->whereIn('field_key', self::UTIL_KEYS)->delete();
        }
        if (Schema::hasTable('email_templates')) {
            DB::table('email_templates')->where('key', self::TEMPLATE_KEY)->delete();
        }
    }
};
