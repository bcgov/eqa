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

    public function test_ministry_sets_the_business_owner_and_primary_contact_on_create_and_edit(): void
    {
        $admin = $this->asMinistry();
        $admin->post('/admin/institutions', $this->validForm([
            'business_owner' => 'Olive Owner', 'primary_contact' => 'Pat Primary', 'primary_contact_email' => 'pat@new.example',
        ]))->assertSessionHas('success');
        $institution = DB::table('institutions')->where('name', 'New College')->first();
        $this->assertSame('Olive Owner', $institution->business_owner);
        $this->assertSame('Pat Primary', $institution->primary_contact);
        $this->assertSame('pat@new.example', $institution->primary_contact_email);

        $admin->put("/admin/institutions/{$institution->crm_id}", [
            'name' => 'New College', 'primary_contact_email' => 'not-an-email',
        ])->assertSessionHasErrors('primary_contact_email');

        $admin->put("/admin/institutions/{$institution->crm_id}", [
            'name' => 'New College', 'business_owner' => 'Owen Owner', 'primary_contact' => 'Robin Contact', 'primary_contact_email' => 'robin@new.example',
        ])->assertSessionHas('success');
        $institution = DB::table('institutions')->where('crm_id', $institution->crm_id)->first();
        $this->assertSame('Owen Owner', $institution->business_owner);
        $this->assertSame('Robin Contact', $institution->primary_contact);
        $this->assertSame('robin@new.example', $institution->primary_contact_email);
    }

    public function test_institutions_cannot_change_their_business_owner_or_primary_contact(): void
    {
        $crmId = (string) Str::uuid();
        DB::table('institutions')->insert(['crm_id' => $crmId, 'name' => 'Own College', 'business_owner' => 'Olive Owner', 'primary_contact' => 'Pat Primary', 'primary_contact_email' => 'pat@own.example']);
        $user = User::create(['name' => 'inst@own.example', 'email' => 'inst@own.example', 'password' => Str::random(32)]);
        $user->roles()->attach(Role::where('name', Role::INSTITUTION_ADMIN)->value('id'));

        $this->actingAs($user)->withSession(['portal_institution' => $crmId, 'portal_role' => 'institution'])
            ->put('/web/institution', ['name' => 'Own College', 'business_owner' => 'Someone Else', 'primary_contact' => 'X', 'primary_contact_email' => 'x@own.example'])
            ->assertRedirect();

        $institution = DB::table('institutions')->where('crm_id', $crmId)->first();
        $this->assertSame('Olive Owner', $institution->business_owner);
        $this->assertSame('Pat Primary', $institution->primary_contact);
        $this->assertSame('pat@own.example', $institution->primary_contact_email);
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
