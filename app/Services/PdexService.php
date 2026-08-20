<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Talks to the PDEX API using an OAuth2 client-credentials access token, which
 * is cached until shortly before it expires. Mirrors bcgov/nrsts PdexService.
 */
class PdexService
{
    public function token(): ?string
    {
        return Cache::remember('pdex_access_token', now()->addMinutes(50), function (): ?string {
            $response = Http::asForm()->post(config('services.pdex.token_endpoint'), [
                'grant_type' => 'client_credentials',
                'client_id' => config('services.pdex.client_id'),
                'client_secret' => config('services.pdex.client_secret'),
            ]);

            return $response->successful() ? $response->json('access_token') : null;
        });
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $path, array $query = []): ?array
    {
        $token = $this->token();

        if ($token === null) {
            return null;
        }

        $response = Http::withToken($token)
            ->baseUrl((string) config('services.pdex.api_url'))
            ->get(ltrim($path, '/'), $query);

        return $response->successful() ? $response->json() : null;
    }
}
