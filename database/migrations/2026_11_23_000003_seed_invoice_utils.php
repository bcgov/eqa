<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Invoice PDF wording, editable under Maintenance > Invoice. Seeded from config/eqa.php.
return new class extends Migration
{
    private const KEYS = [
        'invoice_title', 'invoice_payment_online', 'invoice_payment_offline', 'invoice_due_text',
        'invoice_paid_message', 'invoice_notes', 'invoice_footer',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('utils')) {
            return;
        }

        $now = Carbon::now();
        $rows = [
            ['invoice_title', 'Document Title', 'eqa.invoice.title', 'text', 'Large heading at the top right of the invoice.'],
            ['invoice_payment_online', 'Online Payment Instructions', 'eqa.invoice.payment_online', 'multiline', 'First paragraph of "How to pay" on unpaid invoices.'],
            ['invoice_payment_offline', 'EFT / Cheque Instructions', 'eqa.invoice.payment_offline', 'multiline', 'Second paragraph of "How to pay": how to pay by EFT or cheque (add payee or remittance details here).'],
            ['invoice_due_text', 'Due Date Line', 'eqa.invoice.due_text', 'text', 'Shown under the payment instructions when the invoice has a due date.'],
            ['invoice_paid_message', 'Paid Heading', 'eqa.invoice.paid_message', 'text', 'Heading of the box shown on paid invoices.'],
            ['invoice_notes', 'Additional Notes', 'eqa.invoice.notes', 'note', 'Optional notes printed on every invoice (e.g. payment terms). Leave blank for none.'],
            ['invoice_footer', 'Page Footer', 'eqa.invoice.footer', 'text', 'Small footer line at the bottom of every page.'],
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
                'sort_order' => 100 + $order,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('utils')) {
            DB::table('utils')->whereIn('field_key', self::KEYS)->delete();
        }
    }
};
