<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// "Per Student Fee Payment Enrollment" on the application (international students on study permits
// over the preceding calendar year; the basis of the per international student fee), and the form
// help bubbles moved to Maintenance > Utils (category Application Help Text).
return new class extends Migration
{
    private const HELP = [
        ['help_total_enrolment', 'Total Institution Enrolment', 'total_enrolment'],
        ['help_intl_students_permit', 'International Students - Study Permit', 'intl_students_permit'],
        ['help_in_person_students', 'Total Number of In-Person Students', 'in_person_students'],
        ['help_online_students', 'Total Number of Online Students', 'online_students'],
        ['help_per_student_fee_enrolment', 'Per Student Fee Payment Enrollment', 'per_student_fee_enrolment'],
    ];

    public function up(): void
    {
        if (Schema::hasTable('applications') && ! Schema::hasColumn('applications', 'per_student_fee_enrolment')) {
            Schema::table('applications', fn (Blueprint $table) => $table->unsignedInteger('per_student_fee_enrolment')->nullable());
        }

        if (! Schema::hasTable('utils')) {
            return;
        }
        $now = Carbon::now();
        foreach (self::HELP as $order => [$key, $label, $field]) {
            if (DB::table('utils')->where('field_key', $key)->exists()) {
                continue;
            }
            DB::table('utils')->insert([
                'field_type' => 'Application Help Text',
                'field_key' => $key,
                'field_label' => $label,
                'field_name' => (string) config('eqa.help.'.$field),
                'field_description' => 'Text of the "?" help bubble next to '.$label.' on the application'.($field === 'per_student_fee_enrolment' ? '.' : ' and institution profile forms.'),
                'field_format' => 'multiline',
                'active_flag' => true,
                'sort_order' => 200 + $order,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('utils')) {
            DB::table('utils')->whereIn('field_key', array_column(self::HELP, 0))->delete();
        }
        if (Schema::hasTable('applications') && Schema::hasColumn('applications', 'per_student_fee_enrolment')) {
            Schema::table('applications', fn (Blueprint $table) => $table->dropColumn('per_student_fee_enrolment'));
        }
    }
};
