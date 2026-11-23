<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Applications returned to the institution for more information get their own workflow stage
// (changes_requested, status "Additional Information Required") instead of being a Draft with the
// need_additional_details flag set. Moves applications already waiting on the institution.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('applications')) {
            return;
        }

        DB::table('applications')
            ->where('workflow_stage', 'draft')
            ->where('need_additional_details', true)
            ->update(['workflow_stage' => 'changes_requested', 'status' => 'Additional Information Required']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('applications')) {
            return;
        }

        DB::table('applications')
            ->where('workflow_stage', 'changes_requested')
            ->update(['workflow_stage' => 'draft', 'status' => 'Draft', 'need_additional_details' => true]);
    }
};
