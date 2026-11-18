<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EqaRegistryReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_is_a_public_pdf_listing_designated_then_no_longer_designated_institutions(): void
    {
        $this->institution('Zeta College', 'Designated', ['designation_start' => '2014-09-23', 'website' => 'www.zeta.ca']);
        $this->institution('Alpha College', 'Designated', ['designation_start' => '2010-01-11']);
        $this->institution('Gone College', 'De-Designated', ['de_designated_date' => '2025-03-01']);
        $this->institution('Refused College', 'Not Approved', ['designation_expiry' => '2024-06-30']);
        $this->institution('Waiting College', 'Pending');

        $html = $this->viewData();
        $this->assertSame(['Alpha College', 'Zeta College'], array_column($html['designated'], 'name'));
        $this->assertSame('09/23/2014', $html['designated'][1]['date']);
        $this->assertSame('http://www.zeta.ca', $html['designated'][1]['website_url']);
        $this->assertSame(['Gone College', 'Refused College'], array_column($html['noLongerDesignated'], 'name'));
        $this->assertSame(['03/01/2025', '06/30/2024'], array_column($html['noLongerDesignated'], 'date'));

        $response = $this->get('/admin/report/institutions-eqa-registry');

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    /** @return array<string, mixed> */
    private function viewData(): array
    {
        $captured = [];
        view()->composer('reports.eqa-registry', function ($view) use (&$captured): void {
            $captured = $view->getData();
        });
        $this->get('/admin/report/institutions-eqa-registry')->assertOk();

        return $captured;
    }

    /** @param  array<string, mixed>  $extra */
    private function institution(string $name, string $status, array $extra = []): void
    {
        DB::table('institutions')->insert(array_merge([
            'crm_id' => (string) Str::uuid(),
            'name' => $name,
            'eqa_status' => $status,
            'street1' => '123 Main St',
            'city' => 'Victoria',
            'province' => 'BC',
            'postal_code' => 'V8W 1A1',
            'country' => 'CA',
        ], $extra));
    }
}
