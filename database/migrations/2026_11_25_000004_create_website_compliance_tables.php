<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Website Compliance: the checklist (sections + items) is maintained by Ministry Admins under
// Maintenance; each application keeps its own copy of the checklist with the institution's link
// and/or comment per item (application_web_compliances), so later checklist changes never alter it.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('website_compliance_sections')) {
            Schema::create('website_compliance_sections', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->text('intro')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('website_compliance_items')) {
            Schema::create('website_compliance_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('section_id')->constrained('website_compliance_sections');
                $table->text('label');
                $table->text('help_text')->nullable();
                $table->string('applies_to', 20)->default('all');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('application_web_compliances')) {
            Schema::create('application_web_compliances', function (Blueprint $table): void {
                $table->id();
                $table->uuid('application_crm_id')->index();
                // Source checklist item (informational only: the copied text below is what the application shows).
                $table->unsignedBigInteger('website_compliance_item_id')->nullable()->index();
                $table->string('section_title');
                $table->text('section_intro')->nullable();
                $table->unsignedSmallInteger('section_order')->default(0);
                $table->text('item_label');
                $table->text('item_help_text')->nullable();
                $table->unsignedSmallInteger('item_order')->default(0);
                $table->string('url', 2048)->nullable();
                $table->text('comment')->nullable();
                $table->timestamps();
            });
        }

        if (DB::table('website_compliance_sections')->count() === 0) {
            $this->seed();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('application_web_compliances');
        Schema::dropIfExists('website_compliance_items');
        Schema::dropIfExists('website_compliance_sections');
    }

    private function seed(): void
    {
        $sections = [
            [
                'title' => 'Information available regarding',
                'intro' => 'For each item, include a direct link to where this is found on your website (link to the web page, not simply to a document) and/or a comment noting where it is found.',
                'items' => [
                    ['Programs information', 'Information should include: program names, admission requirements, credential granted, PGWP eligibility, if PTIRU institution, listing if approved or ANR, class schedules. If this information exists in multiple places, include that as a comment in the comment field.'],
                    ['Tuition and Fee Amounts', null],
                    ['The availability of, and eligibility requirements for, financial assistance, scholarships, bursaries and awards provided by the institution', null],
                    ['The availability of, and eligibility requirements for, housing assistance provided by the institution, including housing placement services', null],
                    ['The availability of health, safety, mental health, addiction, and overdose services or supports provided by the institution, including where a student can access naloxone in the event of an overdose emergency', null],
                    ['The availability of learning supports provided by the institution, including academic assistance, program progression assistance, and tutoring', null],
                    ['Resources about living in British Columbia', 'Resources should include: transportation, cellular and internet services, financial institutions and childcare.'],
                    ['Names and business contact information of institutional leadership and administrators', null],
                    ["Information on the institution's orientation program", null],
                    ['On campus facilities available to students', null],
                ],
            ],
            [
                'title' => 'Institution Policies',
                'intro' => null,
                'items' => [
                    ['Student Withdrawal/Dismissal', null],
                    ['Refund Policy', null],
                    ['Dispute Resolution Policy', null],
                    ['Sexual Misconduct Policy', null],
                    ['Bullying and Harassment Policy', null],
                    ['Anti-Racism Policy', null],
                    ['Emergency Preparedness/Response', null],
                ],
            ],
        ];

        foreach ($sections as $s => $section) {
            $sectionId = DB::table('website_compliance_sections')->insertGetId([
                'title' => $section['title'],
                'intro' => $section['intro'],
                'sort_order' => $s + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($section['items'] as $i => [$label, $help]) {
                DB::table('website_compliance_items')->insert([
                    'section_id' => $sectionId,
                    'label' => $label,
                    'help_text' => $help,
                    'applies_to' => 'all',
                    'sort_order' => $i + 1,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
