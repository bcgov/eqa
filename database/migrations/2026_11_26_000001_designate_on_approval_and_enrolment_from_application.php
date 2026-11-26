<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// - prior_eqa_status: the institution's EQA Status before a not-yet-designated institution was designated
//   on approval, so an administrative fee that is never paid can undo that designation.
// - Institution enrolment now follows the most recent approved application; fill institutions whose
//   enrolment fields are still empty from that application (values already on the profile are kept).
return new class extends Migration
{
    private const ENROLMENT = ['total_enrolment', 'intl_students_permit', 'in_person_students', 'online_students'];

    public function up(): void
    {
        if (Schema::hasTable('applications') && ! Schema::hasColumn('applications', 'prior_eqa_status')) {
            Schema::table('applications', fn (Blueprint $table) => $table->string('prior_eqa_status')->nullable());
        }

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
                if ($institution->{$field} === null && $application->{$field} !== null) {
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
        if (Schema::hasTable('applications') && Schema::hasColumn('applications', 'prior_eqa_status')) {
            Schema::table('applications', fn (Blueprint $table) => $table->dropColumn('prior_eqa_status'));
        }
    }
};
