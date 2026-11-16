<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class PdexLoginPayloadLogTest extends TestCase
{
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
