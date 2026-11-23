<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Reorders the application workflow to Draft -> Application Fee Required -> Fee Payment ->
// Pending Review -> Under Review, so an application is only Pending Review once its fee is paid.
// Existing applications are moved onto the equivalent new stage.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('applications')) {
            return;
        }

        // Submitted but the application fee was never paid: now "Application Fee Required".
        DB::table('applications')
            ->where('workflow_stage', 'pending_review')
            ->where(fn (Builder $q) => $q->whereNull('fees_payment_received')->orWhere('fees_payment_received', false))
            ->when(Schema::hasTable('invoices'), fn (Builder $q) => $q->whereNotExists(fn (Builder $i) => $i
                ->from('invoices')
                ->whereColumn('invoices.application_crm_id', 'applications.crm_id')
                ->whereIn('invoices.fee_type', ['application_new', 'application_renewal'])
                ->where('invoices.invoice_status', 'Paid')))
            ->update(['workflow_stage' => 'fee_required', 'status' => 'Application Fee Required']);

        // The old "Fees Payment" stage meant the fee was already recorded and review was next,
        // which is the new "Pending Review" stage. Their status was already "Pending Review".
        DB::table('applications')
            ->where('workflow_stage', 'fees_payment')
            ->update(['workflow_stage' => 'pending_review']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('applications')) {
            return;
        }

        // Best effort: the old flow had no "fee required" or "fee received" status.
        DB::table('applications')
            ->where('workflow_stage', 'fee_required')
            ->update(['workflow_stage' => 'pending_review', 'status' => 'Pending Review']);
        DB::table('applications')
            ->where('workflow_stage', 'fees_payment')
            ->update(['workflow_stage' => 'pending_review', 'status' => 'Pending Review']);
    }
};
