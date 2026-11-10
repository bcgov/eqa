<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IsActive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WebApplicationAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(IsActive::class);

        DB::table('institutions')->insert([
            ['crm_id' => 'inst-mine', 'name' => 'Mine'],
            ['crm_id' => 'inst-other', 'name' => 'Other'],
        ]);
        DB::table('applications')->insert([
            ['crm_id' => 'app-mine', 'institution_crm_id' => 'inst-mine', 'reference' => 'APP-1'],
            ['crm_id' => 'app-other', 'institution_crm_id' => 'inst-other', 'reference' => 'APP-2'],
        ]);
    }

    public function test_institution_can_view_its_own_application(): void
    {
        $this->withSession(['portal_institution' => 'inst-mine'])
            ->withHeaders($this->inertiaHeaders())
            ->get('/web/applications/app-mine')
            ->assertOk()
            ->assertJsonPath('props.application.reference', 'APP-1');
    }

    public function test_institution_cannot_view_another_institutions_application(): void
    {
        $this->withSession(['portal_institution' => 'inst-mine'])
            ->get('/web/applications/app-other')
            ->assertNotFound();
    }

    public function test_application_view_requires_a_portal_institution(): void
    {
        $this->get('/web/applications/app-mine')->assertForbidden();
    }

    /** @return array<string, string> */
    private function inertiaHeaders(): array
    {
        $version = (string) app(HandleInertiaRequests::class)->version(request());

        return ['X-Inertia' => 'true', 'X-Inertia-Version' => $version];
    }
}
