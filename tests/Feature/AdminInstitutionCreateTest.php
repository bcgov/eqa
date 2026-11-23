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

    public function test_ministry_user_can_create_institution_with_a_generated_business_guid(): void
    {
        $response = $this->asMinistry()->post('/admin/institutions', $this->validForm(['bceid_business_guid' => self::GUID]));

        $institution = DB::table('institutions')->where('name', 'New College')->first();
        $this->assertNotNull($institution);
        $response->assertRedirect("/admin/institutions/{$institution->crm_id}");
        $this->assertTrue(Str::isUuid($institution->bceid_business_guid));
        $this->assertSame(Str::lower($institution->bceid_business_guid), $institution->bceid_business_guid);
        $this->assertNotSame(Str::lower(self::GUID), $institution->bceid_business_guid);
        $this->assertSame('Vancouver', $institution->city);
        $this->assertSame('ID-0000 001', $institution->institution_number);
        $this->assertSame('Pending', $institution->eqa_status);
    }

    public function test_generated_business_guid_skips_values_already_in_use(): void
    {
        $taken = '00000000-0000-4000-8000-000000000001';
        $fresh = '00000000-0000-4000-8000-000000000002';
        DB::table('institutions')->insert(['crm_id' => (string) Str::uuid(), 'name' => 'Existing', 'bceid_business_guid' => $taken]);
        Str::createUuidsUsingSequence(['11111111-1111-4111-8111-111111111111', strtoupper($taken), $fresh]);

        try {
            $this->asMinistry()->post('/admin/institutions', $this->validForm());
        } finally {
            Str::createUuidsNormally();
        }

        $this->assertSame($fresh, DB::table('institutions')->where('name', 'New College')->value('bceid_business_guid'));
    }

    public function test_institution_number_is_generated_from_the_largest_existing_number(): void
    {
        DB::table('institutions')->insert(['crm_id' => (string) Str::uuid(), 'name' => 'Existing', 'institution_number' => 'ID-1234 567']);

        $asMinistry = $this->asMinistry();
        $asMinistry->post('/admin/institutions', $this->validForm());
        $asMinistry->post('/admin/institutions', $this->validForm([
            'name' => 'Manual College',
            'institution_number' => 'ID-9000 000',
        ]));

        $this->assertSame('ID-1234 568', DB::table('institutions')->where('name', 'New College')->value('institution_number'));
        $this->assertSame('ID-1234 569', DB::table('institutions')->where('name', 'Manual College')->value('institution_number'));
    }

    public function test_required_fields_are_validated(): void
    {
        $form = $this->validForm(['street1' => '', 'legal_name' => '']);

        $this->asMinistry()->post('/admin/institutions', $form)
            ->assertSessionHasErrors(['street1', 'legal_name'])
            ->assertSessionDoesntHaveErrors(['name', 'city', 'province', 'postal_code', 'country', 'bceid_business_guid']);

        $this->assertSame(0, DB::table('institutions')->count());
    }

    /** @param  array<string, mixed>  $overrides */
    private function validForm(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New College',
            'legal_name' => 'New College Ltd.',
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
