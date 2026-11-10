<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IsActive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Modules\Admin\Services\DesignationService;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(IsActive::class);
        Carbon::setTestNow('2026-11-02 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_reports_page_lists_all_five_reports(): void
    {
        $this->report('')->assertJsonCount(5, 'props.reports')->assertJsonPath('props.report', null);
    }

    public function test_unknown_report_is_not_found(): void
    {
        $this->get('/admin/reports/nope')->assertNotFound();
        $this->get('/admin/reports/nope/csv')->assertNotFound();
    }

    public function test_designated_institutions_include_the_named_primary_contact(): void
    {
        $this->institution('inst-a', 'Alpha College', [
            'qa_met_through' => 'Public Institution Legislation',
            'dli_number' => 'O123',
            'street1' => '1 Main St',
            'street2' => 'Suite 2',
            'city' => 'Victoria',
            'postal_code' => 'V8V 1A1',
            'primary_contact' => 'Pat  Contact',
        ]);
        $this->contact('inst-a', 'Admin Person', 'admin@alpha.test', ['role' => 'Admin', 'web_user_active' => true]);
        $this->contact('inst-a', 'Pat Contact', 'pat@alpha.test', ['first_name' => 'Pat', 'last_name' => 'Contact', 'job_title' => 'Registrar']);
        $this->institution('inst-b', 'Beta Pending', ['eqa_status' => 'Pending']);

        $this->report('designated-institutions')
            ->assertJsonCount(1, 'props.report.rows')
            ->assertJsonPath('props.report.rows.0.name', 'Alpha College')
            ->assertJsonPath('props.report.rows.0.dli_number', 'O123')
            ->assertJsonPath('props.report.rows.0.street', '1 Main St, Suite 2')
            ->assertJsonPath('props.report.rows.0.contact_job_title', 'Registrar')
            ->assertJsonPath('props.report.rows.0.contact_email', 'pat@alpha.test');

        $this->report('primary-contacts')
            ->assertJsonPath('props.report.rows.0.first_name', 'Pat')
            ->assertJsonPath('props.report.rows.0.last_name', 'Contact')
            ->assertJsonPath('props.report.rows.0.email', 'pat@alpha.test');
    }

    public function test_primary_contact_falls_back_to_the_portal_admin_when_none_is_named(): void
    {
        $this->institution('inst-a', 'Alpha College');
        $this->contact('inst-a', 'Inactive User', 'old@alpha.test', ['web_user_active' => false]);
        $this->contact('inst-a', 'Ada Admin', 'ada@alpha.test', ['first_name' => 'Ada', 'last_name' => 'Admin', 'role' => 'Admin', 'web_user_active' => true]);

        $this->report('primary-contacts')
            ->assertJsonPath('props.report.rows.0.first_name', 'Ada')
            ->assertJsonPath('props.report.rows.0.email', 'ada@alpha.test');
    }

    public function test_expiry_report_orders_by_soonest_expiry(): void
    {
        $this->institution('inst-a', 'Later', ['designation_expiry' => '2027-06-01']);
        $this->institution('inst-b', 'Sooner', ['designation_expiry' => '2026-12-01']);

        $this->report('designation-expiry')
            ->assertJsonPath('props.report.rows.0.name', 'Sooner')
            ->assertJsonPath('props.report.rows.0.designation_expiry', '2026-12-01')
            ->assertJsonPath('props.report.rows.1.name', 'Later');
    }

    public function test_de_designation_is_dated_and_filtered_by_period(): void
    {
        $this->institution('inst-a', 'Recently Removed');
        $this->institution('inst-b', 'Legacy Removed', ['eqa_status' => 'De-Designated', 'designation_expiry' => '2025-03-31']);
        $this->institution('inst-c', 'Still Designated');

        app(DesignationService::class)->applyInstitutionDesignation('inst-a', ['eqa_status' => 'De-Designated']);
        $this->assertSame('2026-11-02', DB::table('institutions')->where('crm_id', 'inst-a')->value('de_designated_date'));

        $this->report('de-designated')
            ->assertJsonCount(2, 'props.report.rows')
            ->assertJsonPath('props.report.rows.0.de_designated_date', '2026-11-02')
            ->assertJsonPath('props.report.rows.1.name', 'Legacy Removed')
            ->assertJsonPath('props.report.rows.1.de_designated_date', '2025-03-31');

        $this->report('de-designated', ['from' => '2026-01-01', 'to' => '2026-12-31'])
            ->assertJsonCount(1, 'props.report.rows')
            ->assertJsonPath('props.report.rows.0.name', 'Recently Removed');

        app(DesignationService::class)->applyInstitutionDesignation('inst-a', ['eqa_status' => 'Designated']);
        $this->assertNull(DB::table('institutions')->where('crm_id', 'inst-a')->value('de_designated_date'));
    }

    public function test_de_designated_period_must_be_in_order(): void
    {
        $this->get('/admin/reports/de-designated?from=2026-12-31&to=2026-01-01')->assertSessionHasErrors('to');
    }

    public function test_active_applications_exclude_finalized_ones(): void
    {
        $this->institution('inst-a', 'Alpha College');
        DB::table('applications')->insert([
            ['crm_id' => 'app-1', 'institution_crm_id' => 'inst-a', 'reference' => 'APP-1', 'status' => 'Under Review', 'workflow_stage' => 'under_review', 'application_date' => '2026-10-01'],
            ['crm_id' => 'app-2', 'institution_crm_id' => 'inst-a', 'reference' => 'APP-2', 'status' => 'Approved', 'workflow_stage' => 'end', 'application_date' => '2025-10-01'],
        ]);

        $this->report('active-applications')
            ->assertJsonCount(1, 'props.report.rows')
            ->assertJsonPath('props.report.rows.0.name', 'Alpha College')
            ->assertJsonPath('props.report.rows.0.status', 'Under Review')
            ->assertJsonPath('props.report.rows.0.application_date', '2026-10-01');
    }

    public function test_csv_export_neutralises_formulas(): void
    {
        $this->institution('inst-a', '=HYPERLINK("http://evil.test")', ['designation_expiry' => '2027-01-01']);

        $response = $this->get('/admin/reports/designation-expiry/csv')->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('designation-expiry-2026-11-02.csv', (string) $response->headers->get('Content-Disposition'));
        $lines = preg_split('/\r?\n/', trim(substr($response->streamedContent(), 3)));
        $this->assertSame(['Institution Name', 'QA Met Through', 'Current Expiry Date'], str_getcsv($lines[0], escape: ''));
        $this->assertSame(['\'=HYPERLINK("http://evil.test")', '', '2027-01-01'], str_getcsv($lines[1], escape: ''));
    }

    /** @param  array<string, mixed>  $attributes */
    private function institution(string $crmId, string $name, array $attributes = []): void
    {
        DB::table('institutions')->insert(array_merge(['crm_id' => $crmId, 'name' => $name, 'eqa_status' => 'Designated'], $attributes));
    }

    /** @param  array<string, mixed>  $attributes */
    private function contact(string $institution, string $fullName, string $email, array $attributes = []): void
    {
        static $n = 0;
        DB::table('institution_users')->insert(array_merge([
            'crm_id' => 'contact-'.(++$n),
            'institution_crm_id' => $institution,
            'full_name' => $fullName,
            'email' => $email,
        ], $attributes));
    }

    /** @param  array<string, string>  $query */
    private function report(string $key, array $query = []): TestResponse
    {
        $version = (string) app(HandleInertiaRequests::class)->version(request());
        $url = rtrim('/admin/reports/'.$key, '/').($query ? '?'.http_build_query($query) : '');

        return $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])->get($url)->assertOk();
    }
}
