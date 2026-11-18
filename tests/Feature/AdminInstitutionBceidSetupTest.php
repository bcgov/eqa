<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\IsActive;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Admin\Services\BceidService;
use Modules\Admin\Services\ProcessNotifier;
use Tests\TestCase;

class AdminInstitutionBceidSetupTest extends TestCase
{
    use RefreshDatabase;

    private const BUSINESS_GUID = '3F2504E0-4F89-11D3-9A0C-0305E82C3301';

    private string $crmId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(IsActive::class);
        $this->crmId = (string) Str::uuid();
        DB::table('institutions')->insert(['crm_id' => $this->crmId, 'name' => 'New College']);
    }

    public function test_fetch_holds_the_bceid_result_in_session_without_writing_to_the_database(): void
    {
        $this->mockBceid();

        $this->asMinistry()->post("/admin/institutions/{$this->crmId}/bceid-lookup", ['bceid_username' => 'admin01'])
            ->assertRedirect("/admin/institutions/{$this->crmId}")
            ->assertSessionHas('success');

        $this->assertNull(DB::table('institutions')->where('crm_id', $this->crmId)->value('bceid_business_guid'));
        $this->assertSame(0, DB::table('institution_users')->count());
    }

    public function test_fetch_is_rejected_when_the_institution_already_has_a_location(): void
    {
        DB::table('campuses')->insert(['crm_id' => (string) Str::uuid(), 'institution_crm_id' => $this->crmId, 'name' => 'Main']);
        $this->mockBceid();

        $this->asMinistry()->post("/admin/institutions/{$this->crmId}/bceid-lookup", ['bceid_username' => 'admin01'])
            ->assertSessionHas('error');

        $this->assertNull(session()->get("bceid_lookup.{$this->crmId}"));
    }

    public function test_fetch_is_rejected_when_the_bceid_user_is_already_a_contact_elsewhere(): void
    {
        $otherCrmId = (string) Str::uuid();
        DB::table('institutions')->insert(['crm_id' => $otherCrmId, 'name' => 'Other College']);
        DB::table('institution_users')->insert([
            'crm_id' => (string) Str::uuid(),
            'institution_crm_id' => $otherCrmId,
            'bceid_user_guid' => 'USER-GUID-1',
        ]);
        $this->mockBceid();

        $this->asMinistry()->post("/admin/institutions/{$this->crmId}/bceid-lookup", ['bceid_username' => 'admin01'])
            ->assertSessionHas('error', 'This BCeID user is already a contact at another institution.');

        $this->assertNull(session()->get("bceid_lookup.{$this->crmId}"));
    }

    public function test_confirm_links_the_business_guid_and_creates_the_administrator_contact(): void
    {
        DB::table('institutions')->where('crm_id', $this->crmId)->update(['bceid_business_guid' => (string) Str::uuid()]);
        $this->mockBceid();
        $user = $this->ministryUser();

        $this->actingAs($user)->withSession(['portal_role' => 'ministry'])
            ->post("/admin/institutions/{$this->crmId}/bceid-lookup", ['bceid_username' => 'admin01']);

        $this->actingAs($user)->withSession(['portal_role' => 'ministry'])
            ->post("/admin/institutions/{$this->crmId}/bceid-lookup/confirm")
            ->assertSessionHas('success');

        $this->assertSame(
            Str::lower(self::BUSINESS_GUID),
            DB::table('institutions')->where('crm_id', $this->crmId)->value('bceid_business_guid')
        );
        $contact = DB::table('institution_users')->where('institution_crm_id', $this->crmId)->first();
        $this->assertNotNull($contact);
        $this->assertSame('admin01', $contact->bceid_username);
        $this->assertSame('user-guid-1', $contact->bceid_user_guid);
        $this->assertSame('Ada', $contact->first_name);
        $this->assertSame('Admin', $contact->role);
        $this->assertNull(session()->get("bceid_lookup.{$this->crmId}"));

        $account = User::where('bceid_user_guid', 'user-guid-1')->first();
        $this->assertNotNull($account);
        $this->assertSame('ada@example.test', $account->email);
        $this->assertSame('ADMIN01', $account->bceid_username);
        $this->assertFalse($account->disabled);
        $this->assertTrue($account->hasRole(Role::INSTITUTION_ADMIN));
        $this->assertSame('ID-0000 001', DB::table('institutions')->where('crm_id', $this->crmId)->value('institution_number'));
    }

    public function test_confirm_generates_the_next_institution_number_and_reuses_an_existing_account(): void
    {
        DB::table('institutions')->insert([
            ['crm_id' => (string) Str::uuid(), 'name' => 'A', 'institution_number' => 'ID-1234 567'],
            ['crm_id' => (string) Str::uuid(), 'name' => 'B', 'institution_number' => 'ID-0999 999'],
        ]);
        $existing = User::create([
            'name' => 'Ada', 'email' => 'old@example.test', 'password' => Str::random(32),
            'bceid_user_guid' => 'USER-GUID-1', 'disabled' => true,
        ]);
        $existing->roles()->attach(Role::firstOrCreate(['name' => Role::INSTITUTION_GUEST])->id);
        $this->mockBceid();
        $user = $this->ministryUser();

        $this->actingAs($user)->withSession(['portal_role' => 'ministry'])
            ->post("/admin/institutions/{$this->crmId}/bceid-lookup", ['bceid_username' => 'admin01']);
        $this->actingAs($user)->withSession(['portal_role' => 'ministry'])
            ->post("/admin/institutions/{$this->crmId}/bceid-lookup/confirm")
            ->assertSessionHas('success');

        $this->assertSame('ID-1234 568', DB::table('institutions')->where('crm_id', $this->crmId)->value('institution_number'));
        $this->assertSame(2, User::count());
        $existing->refresh()->load('roles');
        $this->assertFalse($existing->disabled);
        $this->assertTrue($existing->hasRole(Role::INSTITUTION_ADMIN));
        $this->assertFalse($existing->hasRole(Role::INSTITUTION_GUEST));
    }

    public function test_confirm_is_rejected_when_a_new_account_email_is_already_taken(): void
    {
        User::create(['name' => 'Other', 'email' => 'ADA@example.test', 'password' => Str::random(32)]);
        $this->mockBceid();
        $user = $this->ministryUser();

        $this->actingAs($user)->withSession(['portal_role' => 'ministry'])
            ->post("/admin/institutions/{$this->crmId}/bceid-lookup", ['bceid_username' => 'admin01']);
        $this->actingAs($user)->withSession(['portal_role' => 'ministry'])
            ->post("/admin/institutions/{$this->crmId}/bceid-lookup/confirm")
            ->assertSessionHas('error');

        $this->assertSame(0, DB::table('institution_users')->count());
        $this->assertNull(DB::table('institutions')->where('crm_id', $this->crmId)->value('bceid_business_guid'));
    }

    public function test_confirm_is_rejected_when_the_business_guid_was_claimed_after_the_fetch(): void
    {
        $this->mockBceid();
        $user = $this->ministryUser();

        $this->actingAs($user)->withSession(['portal_role' => 'ministry'])
            ->post("/admin/institutions/{$this->crmId}/bceid-lookup", ['bceid_username' => 'admin01']);

        DB::table('institutions')->insert([
            'crm_id' => (string) Str::uuid(),
            'name' => 'Other College',
            'bceid_business_guid' => Str::lower(self::BUSINESS_GUID),
        ]);

        $this->actingAs($user)->withSession(['portal_role' => 'ministry'])
            ->post("/admin/institutions/{$this->crmId}/bceid-lookup/confirm")
            ->assertSessionHas('error');

        $this->assertNull(DB::table('institutions')->where('crm_id', $this->crmId)->value('bceid_business_guid'));
        $this->assertSame(0, DB::table('institution_users')->count());
    }

    private function mockBceid(): void
    {
        $this->mock(BceidService::class, function ($mock): void {
            $mock->shouldReceive('search')->andReturn([
                'found' => true,
                'user_guid' => 'user-guid-1',
                'business_guid' => self::BUSINESS_GUID,
                'business_legal_name' => 'New College Ltd.',
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'email' => 'ada@example.test',
                'message' => null,
            ]);
        });
        $this->mock(ProcessNotifier::class, fn ($mock) => $mock->shouldReceive('portalUserCreated')->andReturnNull());
    }

    private function ministryUser(): User
    {
        $user = User::create(['name' => 'staff@example.test', 'email' => 'staff@example.test', 'password' => Str::random(32)]);
        $user->roles()->attach(Role::where('name', Role::MINISTRY_ADMIN)->value('id'));

        return $user;
    }

    private function asMinistry(): static
    {
        return $this->actingAs($this->ministryUser())->withSession(['portal_role' => 'ministry']);
    }
}
