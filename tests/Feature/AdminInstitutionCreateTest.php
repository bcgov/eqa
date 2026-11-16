<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\IsActive;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminInstitutionCreateTest extends TestCase
{
    use RefreshDatabase;

    private const GUID = '3F2504E0-4F89-11D3-9A0C-0305E82C3301';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(IsActive::class);
    }

    public function test_ministry_user_can_create_institution_with_business_guid(): void
    {
        $response = $this->asMinistry()->post('/admin/institutions', $this->validForm());

        $institution = DB::table('institutions')->where('name', 'New College')->first();
        $this->assertNotNull($institution);
        $response->assertRedirect("/admin/institutions/{$institution->crm_id}");
        $this->assertSame('3f2504e0-4f89-11d3-9a0c-0305e82c3301', $institution->bceid_business_guid);
        $this->assertSame('Vancouver', $institution->city);
    }

    public function test_required_fields_and_business_guid_format_are_validated(): void
    {
        $form = $this->validForm(['bceid_business_guid' => 'not-a-guid', 'street1' => '', 'legal_name' => '']);

        $this->asMinistry()->post('/admin/institutions', $form)
            ->assertSessionHasErrors(['bceid_business_guid', 'street1', 'legal_name'])
            ->assertSessionDoesntHaveErrors(['name', 'city', 'province', 'postal_code', 'country']);

        $this->assertSame(0, DB::table('institutions')->count());
    }

    public function test_business_guid_already_used_by_another_institution_is_rejected(): void
    {
        DB::table('institutions')->insert([
            'crm_id' => (string) Str::uuid(),
            'name' => 'Existing College',
            'bceid_business_guid' => Str::lower(self::GUID),
        ]);

        $this->asMinistry()->post('/admin/institutions', $this->validForm(['bceid_business_guid' => self::GUID]))
            ->assertSessionHasErrors('bceid_business_guid');

        $this->assertSame(1, DB::table('institutions')->count());
    }

    /** @param  array<string, mixed>  $overrides */
    private function validForm(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New College',
            'legal_name' => 'New College Ltd.',
            'bceid_business_guid' => self::GUID,
            'street1' => '123 Main St',
            'city' => 'Vancouver',
            'province' => 'BC',
            'postal_code' => 'V6B 1A1',
            'country' => 'Canada',
        ], $overrides);
    }

    private function asMinistry(): static
    {
        $user = User::create(['name' => 'staff@example.test', 'email' => 'staff@example.test', 'password' => Str::random(32)]);
        $user->roles()->attach(Role::where('name', Role::MINISTRY_ADMIN)->value('id'));

        return $this->actingAs($user)->withSession(['portal_role' => 'ministry']);
    }
}
