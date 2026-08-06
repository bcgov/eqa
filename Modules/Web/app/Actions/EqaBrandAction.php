<?php

namespace Modules\Web\Actions;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Invokable action that calls the external EqaBrand service endpoint
 * (ZippedLogoDownload) originally defined in:
 * EQA WEB/Web.TEST.config::service_endpoint::EqaBrand
 *
 * Source lineage:
 *   connector: external_refs
 *   kind:      service_endpoint
 *   ref:       EQA WEB/Web.TEST.config::service_endpoint::EqaBrand
 */
class EqaBrandAction
{
    /**
     * Base address for the EqaBrand external service endpoint.
     */
    protected string $endpoint = 'http://eqa.staging.bayleaf.com:8080/apply/apply/ZippedLogoDownload';

    /**
     * Execute the action: invoke the external EqaBrand service endpoint.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function __invoke(array $payload = []): array
    {
        $response = $this->client()->post($this->endpoint, $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                "EqaBrand service endpoint call failed with status [{$response->status()}]: {$response->body()}"
            );
        }

        return [
            'status' => $response->status(),
            'body' => $response->body(),
            'json' => $response->header('Content-Type') && str_contains($response->header('Content-Type'), 'json')
                ? $response->json()
                : null,
        ];
    }

    /**
     * Build the HTTP client used to call the external service endpoint.
     */
    protected function client(): PendingRequest
    {
        return Http::timeout(30)->acceptJson();
    }
}