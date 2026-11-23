<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Admin-maintained application settings (Maintenance > Utils), modelled on bcgov/fsg's utils
// table. field_key is the fixed name the code reads; field_name holds the value. Seeded from the
// current config/.env values so nothing changes until an admin edits a row.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('utils')) {
            Schema::create('utils', function (Blueprint $table): void {
                $table->id();
                $table->string('field_type');
                $table->string('field_key')->unique();
                $table->string('field_label');
                $table->text('field_name')->nullable();
                $table->text('field_description')->nullable();
                $table->string('field_format')->default('text');
                $table->boolean('active_flag')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        $now = Carbon::now();
        $rows = [
            ['Ministry', 'ministry_name', 'Ministry Name', config('eqa.ministry.name'), 'text', 'Ministry name printed on invoices and the public site footer.'],
            ['Ministry', 'branch_name', 'Branch Name', config('eqa.ministry.branch'), 'text', 'Branch name printed in the invoice issuer block.'],
            ['Ministry', 'program_name', 'Program Name', config('eqa.ministry.program'), 'text', 'Program name printed at the top of invoices.'],
            ['Ministry', 'mailing_address', 'Mailing Address', config('eqa.ministry.address'), 'multiline', 'Ministry mailing address printed on invoices (one line per row).'],
            ['Ministry', 'contact_email', 'Contact Email', config('eqa.ministry.email'), 'email', 'EQA contact email shown on invoices and payment instructions.'],
            ['Ministry', 'privacy_email', 'Privacy Officer Email', config('eqa.ministry.privacy_email'), 'email', 'Privacy officer email in the collection notice of the public site footer.'],
            ['Fees', 'fee_application_new', 'New Application Fee', config('eqa.fees.application_new'), 'money', 'Fixed fee for a first-time application. Applies to applications invoiced after the change.'],
            ['Fees', 'fee_application_renewal', 'Renewal Application Fee', config('eqa.fees.application_renewal'), 'money', 'Fixed fee for a renewal (reapplication). Applies to applications invoiced after the change.'],
            ['Fees', 'fee_appeal', 'Appeal Fee', config('eqa.fees.appeal'), 'money', 'Fixed fee to appeal a declined application.'],
            ['Fees', 'fee_multi_location', 'Multiple Location Fee', config('eqa.fees.multi_location'), 'money', 'Administrative fee for private institutions with more than one active location.'],
            ['Fees', 'fee_per_international_student', 'International Student Fee (per student)', config('eqa.fees.per_international_student'), 'money', 'Administrative fee charged per study permit student.'],
            ['Deadlines', 'deadline_admin_fee_days', 'Administrative Fee Due (days)', config('eqa.deadlines.admin_fee_days'), 'integer', 'Days an institution has to pay the administrative fee after approval.'],
            ['Deadlines', 'deadline_appeal_days', 'Appeal Window (days)', config('eqa.deadlines.appeal_days'), 'integer', 'Days an institution has to appeal a declined application.'],
            ['Deadlines', 'deadline_changes_days', 'Changes Requested Due (days)', config('eqa.deadlines.changes_days'), 'integer', 'Days an institution has to resubmit after changes are requested.'],
        ];

        foreach ($rows as $order => [$type, $key, $label, $value, $format, $description]) {
            if (DB::table('utils')->where('field_key', $key)->exists()) {
                continue;
            }
            DB::table('utils')->insert([
                'field_type' => $type,
                'field_key' => $key,
                'field_label' => $label,
                'field_name' => (string) $value,
                'field_description' => $description,
                'field_format' => $format,
                'active_flag' => true,
                'sort_order' => $order,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('utils');
    }
};
