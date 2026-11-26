<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Application completion deadline: an application must be submitted and its application fee paid
// within N days of being started (Maintenance > Utils), otherwise it expires. Applications already
// in Draft or Application Fee Required get a full period from today, and a reminder email is sent
// before the deadline.
return new class extends Migration
{
    private const TEMPLATE_KEY = 'application_completion_reminder';

    private const UTILS = [
        ['deadline_application_days', 'Application Completion (days)', 'application_days', 'Days an institution has from starting an application (Draft) to submit it and pay the application fee. Unfinished applications then expire.'],
        ['deadline_application_reminder_days', 'Application Completion Reminder (days before)', 'application_reminder_days', 'Days before the application completion deadline that the institution is emailed a reminder. Turn the reminder off under Maintenance > Email Templates.'],
    ];

    public function up(): void
    {
        if (Schema::hasTable('applications')) {
            Schema::table('applications', function (Blueprint $table): void {
                if (! Schema::hasColumn('applications', 'completion_due_date')) {
                    $table->date('completion_due_date')->nullable();
                }
                if (! Schema::hasColumn('applications', 'completion_reminder_sent_at')) {
                    $table->timestamp('completion_reminder_sent_at')->nullable();
                }
            });

            $due = Carbon::now('America/Vancouver')->addDays((int) config('eqa.deadlines.application_days', 30))->toDateString();
            DB::table('applications')
                ->whereIn('workflow_stage', ['draft', 'fee_required'])
                ->whereNull('completion_due_date')
                ->update(['completion_due_date' => $due]);

            if (Schema::hasTable('invoices')) {
                $open = DB::table('applications')->where('workflow_stage', 'fee_required')->where('completion_due_date', $due)->pluck('crm_id');
                DB::table('invoices')
                    ->whereIn('application_crm_id', $open)
                    ->whereIn('fee_type', ['application_new', 'application_renewal'])
                    ->whereNotIn('invoice_status', ['Paid', 'Expired'])
                    ->whereNull('due_date')
                    ->update(['due_date' => $due]);
            }
        }

        if (Schema::hasTable('utils')) {
            $now = Carbon::now();
            foreach (self::UTILS as $order => [$key, $label, $config, $description]) {
                if (DB::table('utils')->where('field_key', $key)->exists()) {
                    continue;
                }
                DB::table('utils')->insert([
                    'field_type' => 'Deadlines',
                    'field_key' => $key,
                    'field_label' => $label,
                    'field_name' => (string) config('eqa.deadlines.'.$config),
                    'field_description' => $description,
                    'field_format' => 'integer',
                    'active_flag' => true,
                    'sort_order' => 14 + $order,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('email_templates') && ! DB::table('email_templates')->where('key', self::TEMPLATE_KEY)->exists()) {
            DB::table('email_templates')->insert([
                'key' => self::TEMPLATE_KEY,
                'name' => 'Application — Completion Deadline Reminder',
                'category' => 'Application Confirmation',
                'subject' => 'EQA Application {{application_reference}} | Complete by {{completion_due_date}}',
                'recipients' => 'EQA@gov.bc.ca',
                'description' => 'Reminder sent before the application completion deadline to an institution whose application is still a draft or whose application fee is unpaid.',
                'variables' => 'contact_name, institution_name, application_reference, completion_due_date, next_step',
                'body' => '<p>Dear {{contact_name}}</p>'.
                    '<p>The EQA application {{application_reference}} for {{institution_name}} is not complete yet. {{next_step}}</p>'.
                    '<p>Applications must be submitted and the application fee paid by <b>{{completion_due_date}}</b>. Applications not completed by this date will expire and a new application will have to be started.</p>'.
                    '<p>Sincerely,</p><p><b><i>Education Quality Assurance</i></b><br>Email: <a href="mailto:EQA@gov.bc.ca">EQA@gov.bc.ca</a></p>',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('email_templates')) {
            DB::table('email_templates')->where('key', self::TEMPLATE_KEY)->delete();
        }
        if (Schema::hasTable('utils')) {
            DB::table('utils')->whereIn('field_key', array_column(self::UTILS, 0))->delete();
        }
        if (Schema::hasTable('applications')) {
            Schema::table('applications', function (Blueprint $table): void {
                foreach (['completion_due_date', 'completion_reminder_sent_at'] as $column) {
                    if (Schema::hasColumn('applications', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
