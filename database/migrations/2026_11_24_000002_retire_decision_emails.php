<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Declines and appeal outcomes are no longer emailed: the institution receives a formal letter
// (from the ADM for appeals) outside the system. Retire the decline template and note on the
// approval templates that they are not sent for an application approved on appeal.
return new class extends Migration
{
    private const APPEAL_NOTE = ' Not sent when the application is approved after an overturned appeal (the ADM sends a formal letter).';

    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        DB::table('email_templates')->where('key', 'application_not_approved')->delete();

        foreach (['application_approved', 'application_approved_fee_due'] as $key) {
            $description = (string) DB::table('email_templates')->where('key', $key)->value('description');
            if ($description !== '' && ! str_contains($description, self::APPEAL_NOTE)) {
                DB::table('email_templates')->where('key', $key)->update(['description' => $description.self::APPEAL_NOTE]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        foreach (['application_approved', 'application_approved_fee_due'] as $key) {
            $description = (string) DB::table('email_templates')->where('key', $key)->value('description');
            DB::table('email_templates')->where('key', $key)->update(['description' => str_replace(self::APPEAL_NOTE, '', $description)]);
        }

        if (! DB::table('email_templates')->where('key', 'application_not_approved')->exists()) {
            DB::table('email_templates')->insert([
                'key' => 'application_not_approved',
                'name' => 'Application — Not Approved',
                'category' => 'Application Decision',
                'subject' => 'EQA Designation Application Decision — {{institution_name}}',
                'recipients' => 'EQA@gov.bc.ca',
                'description' => 'Sent when an application is finalized as Not Approved.',
                'variables' => 'institution_name, application_reference, not_approved_date, reasons',
                'body' => '<p>Dear {{institution_name}},</p>'.
                    '<p>Your Education Quality Assurance (EQA) designation application (<b>{{application_reference}}</b>) was <b>not approved</b> as of {{not_approved_date}}.</p>'.
                    '<p>{{reasons}}</p>'.
                    '<p>Regards,<br>Education Quality Assurance</p>',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
