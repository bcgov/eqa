<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Email templates + a single global "email sending" switch, migrated from the
// legacy Dynamics notification-email plugins (CGI.Plugins/Notification Email).
// Additive + idempotent and NOT owned by the DynamicsDataMigrator, so the seeded
// templates and the (default OFF) toggle survive data reloads.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            Schema::create('email_templates', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->string('category')->nullable();
                $table->string('subject');
                $table->text('body');
                $table->string('recipients')->nullable();
                $table->text('description')->nullable();
                $table->text('variables')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('email_settings')) {
            Schema::create('email_settings', function (Blueprint $table): void {
                $table->id();
                // Master switch — emails are OFF by default until the ministry
                // explicitly enables sending.
                $table->boolean('sending_enabled')->default(false);
                $table->timestamps();
            });
        }

        // Seed the single settings row (OFF).
        if (Schema::hasTable('email_settings') && DB::table('email_settings')->count() === 0) {
            DB::table('email_settings')->insert([
                'sending_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Seed the migrated templates (only if not already present).
        if (Schema::hasTable('email_templates')) {
            foreach ($this->seedTemplates() as $template) {
                $exists = DB::table('email_templates')->where('key', $template['key'])->exists();
                if (! $exists) {
                    DB::table('email_templates')->insert(array_merge($template, [
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('email_settings');
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function seedTemplates(): array
    {
        $eqaMailbox = 'EQA@gov.bc.ca';

        $changeBody = "<p><b>{entity} Name:</b> {{name}} ({{id}})</p>".
            "<p><b><u>{entity}</u></b></p>".
            "<p><b>Modified By:</b> {{modified_by}} &nbsp; <b>Modified On:</b> {{modified_on}}</p>".
            '{{changes}}';

        return [
            [
                'key' => 'institution_change_notification',
                'name' => 'Institution — Change to Key Data Fields',
                'category' => 'Change Notification',
                'subject' => 'Notification of Change to Key Data Fields',
                'recipients' => $eqaMailbox,
                'description' => 'Sent to the EQA mailbox when key institution (account) fields change — Legal Name, BC Incorporation Number, QA Met Through, Web Url, address, Student Enrolment Size, % International Students, Enrolment Type. Migrated from InstitutionNotificationEmail.cs.',
                'variables' => 'name, id, modified_by, modified_on, changes',
                'body' => str_replace('{entity}', 'Institution', $changeBody),
            ],
            [
                'key' => 'campus_change_notification',
                'name' => 'Campus — Change to Key Data Fields',
                'category' => 'Change Notification',
                'subject' => 'Notification of Change to Key Data Fields',
                'recipients' => $eqaMailbox,
                'description' => 'Sent to the EQA mailbox when key campus fields change. Migrated from CampusNotificationEmail.cs.',
                'variables' => 'name, id, modified_by, modified_on, changes',
                'body' => str_replace('{entity}', 'Campus', $changeBody),
            ],
            [
                'key' => 'dba_change_notification',
                'name' => 'DBA — Change to Key Data Fields',
                'category' => 'Change Notification',
                'subject' => 'Notification of Change to Key Data Fields',
                'recipients' => $eqaMailbox,
                'description' => 'Sent to the EQA mailbox when key DBA (Doing Business As) fields change. Migrated from DbaNotificationEmail.cs.',
                'variables' => 'name, id, modified_by, modified_on, changes',
                'body' => str_replace('{entity}', 'DBA', $changeBody),
            ],
            [
                'key' => 'application_approved',
                'name' => 'Application — Designation Approved',
                'category' => 'Application Decision',
                'subject' => 'EQA Designation Application Approved — {{institution_name}}',
                'recipients' => $eqaMailbox,
                'description' => 'Sent when an application is approved and the institution becomes Designated.',
                'variables' => 'institution_name, application_reference, approved_date, designation_expiry',
                'body' => "<p>Dear {{institution_name}},</p>".
                    "<p>Your Education Quality Assurance (EQA) designation application (<b>{{application_reference}}</b>) has been <b>approved</b> as of {{approved_date}}.</p>".
                    "<p>Your EQA designation is valid until <b>{{designation_expiry}}</b>.</p>".
                    '<p>Regards,<br>Education Quality Assurance</p>',
            ],
            [
                'key' => 'application_not_approved',
                'name' => 'Application — Not Approved',
                'category' => 'Application Decision',
                'subject' => 'EQA Designation Application Decision — {{institution_name}}',
                'recipients' => $eqaMailbox,
                'description' => 'Sent when an application is finalized as Not Approved.',
                'variables' => 'institution_name, application_reference, not_approved_date, reasons',
                'body' => "<p>Dear {{institution_name}},</p>".
                    "<p>Your Education Quality Assurance (EQA) designation application (<b>{{application_reference}}</b>) was <b>not approved</b> as of {{not_approved_date}}.</p>".
                    "<p>{{reasons}}</p>".
                    '<p>Regards,<br>Education Quality Assurance</p>',
            ],
        ];
    }
};
