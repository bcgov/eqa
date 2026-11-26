<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Flowchart DRAFT V1.4: the approve decision first invoices the administrative fee (status Fee
// Required) and the approval follows its confirmed payment, so the fee email no longer says
// "Approved". A declined application's status is Declined (it was Not Approved); declines are
// still not emailed (the institution receives a formal letter).
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('applications')) {
            DB::table('applications')->where('status', 'Not Approved')->update(['status' => 'Declined']);
        }

        if (! Schema::hasTable('email_templates')) {
            return;
        }

        DB::table('email_templates')->where('key', 'application_approved_fee_due')->update([
            'name' => 'Application — Administrative Fee Required',
            'subject' => 'EQA Application {{application_reference}} | Administrative Fee Required',
            'description' => 'Sent when the ministry decides to approve an application: the administrative fee invoice is due (status Fee Required). The approval is issued once the payment is confirmed. Not sent after an overturned appeal (the ADM sends a formal letter).',
            'variables' => 'contact_name, institution_name, application_reference, invoice_number, admin_fee, invoice_description, due_date',
            'body' => '<p>Dear {{contact_name}}</p>'.
                '<p>The EQA application {{application_reference}} for {{institution_name}} has been reviewed and can proceed to approval once the administrative fee is paid.</p>'.
                '<p>Please pay the administrative fee of <b>{{admin_fee}}</b> (invoice {{invoice_number}}) through the EQA portal by <b>{{due_date}}</b>. The approval decision is issued once the payment has been confirmed. If the fee is not paid by this date the application will expire.</p>'.
                '<p>Sincerely,</p><p><b><i>Education Quality Assurance</i></b><br>Email: <a href="mailto:EQA@gov.bc.ca">EQA@gov.bc.ca</a></p>',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('applications')) {
            DB::table('applications')->where('status', 'Declined')->whereNull('appeal_decision')->update(['status' => 'Not Approved']);
        }
    }
};
