<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InstitutionDirectoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_lists_only_designated_institutions_by_default(): void
    {
        $this->institution('Beta College', 'Designated', ['city' => 'Victoria', 'dli_number' => 'O123456789012']);
        $this->institution('Alpha College', 'Designated', ['city' => 'Vancouver']);
        $this->institution('Gone College', 'De-Designated');
        $this->institution('Waiting College', 'Pending');

        $this->get('/institution-directory')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Public/InstitutionDirectory', false)
                ->where('institutions.total', 2)
                ->where('institutions.data.0.name', 'Alpha College')
                ->where('institutions.data.1.dli_number', 'O123456789012')
                ->missing('institutions.data.1.institution_number')
                ->where('institution', null));
    }

    public function test_previous_status_lists_only_de_designated_institutions_without_dli(): void
    {
        $this->institution('Alpha College', 'Designated', ['city' => 'Surrey']);
        $this->institution('Gone College', 'De-Designated', ['city' => 'Nanaimo', 'dli_number' => 'O999', 'de_designated_date' => '2025-03-01']);
        $this->institution('Lapsed College', 'De-Designated', ['city' => 'Nanaimo', 'designation_expiry' => '2024-06-30']);
        $refused = $this->institution('Refused College', 'Not Approved', ['city' => 'Kamloops']);

        $this->get('/institution-directory?status=previous')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.status', 'previous')
                ->where('institutions.total', 2)
                ->where('institutions.data.0.name', 'Gone College')
                ->where('institutions.data.0.dli_number', null)
                ->where('institutions.data.0.de_designated_date', 'March 1, 2025')
                ->where('institutions.data.1.de_designated_date', 'June 30, 2024')
                ->where('cities', ['Nanaimo']));

        $this->get("/institution-directory/{$refused}")->assertNotFound();
    }

    public function test_keyword_matches_name_legal_name_dli_and_dba_and_city_matches_locations(): void
    {
        $alpha = $this->institution('Alpha College', 'Designated', ['city' => 'Vancouver', 'legal_name' => 'Alpha Holdings Ltd.', 'dli_number' => 'O111222333']);
        $beta = $this->institution('Beta College', 'Designated', ['city' => 'Victoria']);
        $this->institution('Gone College', 'De-Designated', ['dli_number' => 'O111222999']);
        DB::table('dbas')->insert(['crm_id' => (string) Str::uuid(), 'institution_crm_id' => $beta, 'name' => 'Seaside Academy', 'status' => 'Active']);
        DB::table('campuses')->insert(['crm_id' => (string) Str::uuid(), 'institution_crm_id' => $alpha, 'name' => 'Kelowna campus', 'city' => 'Kelowna', 'status' => 'Active']);

        $names = fn (string $qs) => collect($this->get("/institution-directory?{$qs}")->viewData('page')['props']['institutions']['data'])->pluck('name')->all();

        $this->assertSame(['Alpha College'], $names('q=alpha'));
        $this->assertSame(['Alpha College'], $names('q=holdings'));
        $this->assertSame(['Alpha College'], $names('q=o111222'));
        $this->assertSame([], $names('status=previous&q=o111222'));
        $this->assertSame(['Beta College'], $names('q=seaside'));
        $this->assertSame(['Alpha College'], $names('city=kelowna'));
        $this->assertSame([], $names('q=%25'));
    }

    public function test_detail_shows_public_details_and_active_locations_only(): void
    {
        $crmId = $this->institution('Alpha College', 'Designated', ['legal_name' => 'Alpha Holdings Ltd.', 'dli_number' => 'O111222333', 'website' => 'www.alpha.ca', 'designation_start' => '2014-09-23']);
        DB::table('campuses')->insert([
            ['crm_id' => (string) Str::uuid(), 'institution_crm_id' => $crmId, 'name' => 'Main', 'location_name' => 'Alpha Main', 'city' => 'Victoria', 'province' => 'BC', 'email' => 'info@alpha.ca', 'status' => 'Active', 'primary_location' => true],
            ['crm_id' => (string) Str::uuid(), 'institution_crm_id' => $crmId, 'name' => 'Closed', 'location_name' => 'Old Site', 'city' => 'Nanaimo', 'province' => 'BC', 'email' => null, 'status' => 'Inactive', 'primary_location' => false],
        ]);
        DB::table('institution_users')->insert(['crm_id' => (string) Str::uuid(), 'institution_crm_id' => $crmId, 'email' => 'private@alpha.ca']);

        $response = $this->get("/institution-directory/{$crmId}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('institution.name', 'Alpha College')
                ->where('institution.legal_name', 'Alpha Holdings Ltd.')
                ->where('institution.dli_number', 'O111222333')
                ->where('institution.designation_date', 'September 23, 2014')
                ->has('institution.locations', 1)
                ->where('institution.locations.0.name', 'Alpha Main')
                ->where('institution.locations.0.city', 'Victoria, BC'));

        $this->assertStringNotContainsString('private@alpha.ca', $response->getContent());
    }

    public function test_pending_institutions_are_not_viewable(): void
    {
        $crmId = $this->institution('Waiting College', 'Pending');

        $this->get("/institution-directory/{$crmId}")->assertNotFound();
    }

    /** @param  array<string, mixed>  $extra */
    public function test_detail_lists_previous_names_from_the_latest_submitted_application_and_search_finds_them(): void
    {
        $crmId = $this->institution('Alpha College', 'Designated');
        $older = (string) Str::uuid();
        $latest = (string) Str::uuid();
        $draft = (string) Str::uuid();
        DB::table('applications')->insert([
            ['crm_id' => $older, 'reference' => 'APP-1', 'institution_crm_id' => $crmId, 'workflow_stage' => 'end', 'status' => 'Approved', 'application_date' => '2024-01-10'],
            ['crm_id' => $latest, 'reference' => 'APP-2', 'institution_crm_id' => $crmId, 'workflow_stage' => 'under_review', 'status' => 'Under Review', 'application_date' => '2026-01-10'],
            ['crm_id' => $draft, 'reference' => 'APP-3', 'institution_crm_id' => $crmId, 'workflow_stage' => 'draft', 'status' => 'Draft', 'application_date' => '2026-06-01'],
        ]);
        $recent = Carbon::now('America/Vancouver')->subYears(2)->toDateString();
        $ancient = Carbon::now('America/Vancouver')->subYears(7)->toDateString();
        DB::table('previous_operating_names')->insert([
            ['institution_crm_id' => $crmId, 'application_crm_id' => $older, 'name' => 'Old Application Name', 'used_from' => '2020-01-01', 'used_to' => $recent],
            ['institution_crm_id' => $crmId, 'application_crm_id' => $latest, 'name' => 'Alpha Academy', 'used_from' => '2019-01-01', 'used_to' => $recent],
            ['institution_crm_id' => $crmId, 'application_crm_id' => $latest, 'name' => 'Ancient Name', 'used_from' => '2010-01-01', 'used_to' => $ancient],
            ['institution_crm_id' => $crmId, 'application_crm_id' => $draft, 'name' => 'Draft Only Name', 'used_from' => '2022-01-01', 'used_to' => null],
        ]);

        $this->get("/institution-directory/{$crmId}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('institution.previous_names', 1)
                ->where('institution.previous_names.0.name', 'Alpha Academy')
                ->where('institution.previous_names.0.used_from', 'January 1, 2019'));

        $this->get('/institution-directory?q=academy')->assertInertia(fn (AssertableInertia $page) => $page->where('institutions.total', 1));
        $this->get('/institution-directory?q=draft only')->assertInertia(fn (AssertableInertia $page) => $page->where('institutions.total', 0));
        $this->get('/institution-directory?q=ancient')->assertInertia(fn (AssertableInertia $page) => $page->where('institutions.total', 0));
    }

    private function institution(string $name, string $status, array $extra = []): string
    {
        $crmId = (string) Str::uuid();
        DB::table('institutions')->insert(array_merge([
            'crm_id' => $crmId,
            'name' => $name,
            'eqa_status' => $status,
            'street1' => '123 Main St',
            'city' => 'Victoria',
            'province' => 'BC',
            'postal_code' => 'V8W 1A1',
        ], $extra));

        return $crmId;
    }
}
