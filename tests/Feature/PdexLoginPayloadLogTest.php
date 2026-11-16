<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class PdexLoginPayloadLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_bceid_login_matching_an_institution_business_guid_adds_a_guest_contact(): void
    {
        $crmId = (string) Str::uuid();
        $businessGuid = '3f2504e0-4f89-11d3-9a0c-0305e82c3301';
        DB::table('institutions')->insert(['crm_id' => $crmId, 'name' => 'New College', 'bceid_business_guid' => $businessGuid]);

        $this->post('/pdex-login', [
            'token' => $this->unsignedJwt([
                'aud' => config('services.pdex.jwt_audience'),
                'sub' => 'user-guid-9',
                'bceid_user_guid' => 'user-guid-9',
                'bceid_business_guid' => strtoupper($businessGuid),
                'email' => 'new.person@example.com',
                'given_name' => 'New',
                'family_name' => 'Person',
            ]),
            'user_type' => 'bceid',
        ])->assertOk();

        $this->assertDatabaseHas('institution_users', [
            'institution_crm_id' => $crmId,
            'institution_name' => 'New College',
            'email' => 'new.person@example.com',
            'role' => 'Guest',
            'bceid_user_guid' => 'user-guid-9',
            'bceid_business_guid' => strtoupper($businessGuid),
        ]);
    }

    public function test_first_bceid_login_with_unknown_business_guid_adds_no_contact(): void
    {
        $this->post('/pdex-login', [
            'token' => $this->unsignedJwt([
                'aud' => config('services.pdex.jwt_audience'),
                'sub' => 'user-guid-10',
                'bceid_user_guid' => 'user-guid-10',
                'bceid_business_guid' => 'aaaaaaaa-0000-4000-8000-000000000000',
                'email' => 'stranger@example.com',
            ]),
            'user_type' => 'bceid',
        ]);

        $this->assertDatabaseMissing('institution_users', ['email' => 'stranger@example.com']);
    }

    public function test_pdex_login_logs_decoded_claims_without_the_raw_token(): void
    {
        Log::spy();

        $token = $this->unsignedJwt(['sub' => 'abc-123', 'bceid_user_guid' => 'guid-1']);

        $this->post('/pdex-login', [
            'token' => $token,
            'user_type' => 'bceid',
            'logoutUrl' => 'https://sso.example/logout',
        ]);

        Log::shouldHaveReceived('info')
            ->with('PDEX login payload received', [
                'user_type' => 'bceid',
                'logout_url' => 'https://sso.example/logout',
                'claims' => ['sub' => 'abc-123', 'bceid_user_guid' => 'guid-1'],
            ])
            ->once();

        Log::shouldNotHaveReceived('info', [
            Mockery::any(),
            Mockery::on(fn ($context) => str_contains(json_encode($context), $token)),
        ]);
    }

    private function unsignedJwt(array $claims): string
    {
        $encode = fn (array $data): string => strtr(base64_encode(json_encode($data)), '+/', '-_');

        return $encode(['alg' => 'none', 'typ' => 'JWT']).'.'.$encode($claims).'.sig';
    }
}
