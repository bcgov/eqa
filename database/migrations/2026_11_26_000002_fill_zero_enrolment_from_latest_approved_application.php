<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The institution edit form used to save blank enrolment fields as 0, so the earlier backfill (which
// only filled empty fields) skipped them. Take the enrolment from each institution's most recent
// approved application wherever the profile value is empty or 0.
return new class extends Migration
{
    private const ENROLMENT = ['total_enrolment', 'intl_students_permit', 'in_person_students', 'online_students'];

    public function up(): void
    {
        if (! Schema::hasTable('institutions') || ! Schema::hasTable('applications')) {
            return;
        }

        $approved = DB::table('applications')
            ->whereNotNull('institution_crm_id')
            ->where('status', 'like', 'Approved%')
            ->orderByDesc('approved_date')
            ->orderByDesc('application_date')
            ->get(array_merge(['institution_crm_id'], self::ENROLMENT))
            ->unique('institution_crm_id');

        foreach ($approved as $application) {
            $institution = DB::table('institutions')->where('crm_id', $application->institution_crm_id)->first();
            if ($institution === null) {
                continue;
            }
            $updates = [];
            foreach (self::ENROLMENT as $field) {
                if (in_array($institution->{$field}, [null, 0, '0'], true) && $application->{$field} !== null) {
                    $updates[$field] = $application->{$field};
                }
            }
            if ($updates !== []) {
                DB::table('institutions')->where('crm_id', $application->institution_crm_id)->update($updates);
            }
        }
    }

    public function down(): void
    {
        // Data fix only.
    }
};
