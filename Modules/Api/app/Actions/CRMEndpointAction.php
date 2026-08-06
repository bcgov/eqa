<?php

declare(strict_types=1);

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Invokable action encapsulating outbound calls to the legacy CRM
 * (Dynamics XRM) organization service endpoint that was previously
 * configured as WCF client "CRM_Endpoint" in Web.config.
 *
 * Source lineage: EQA API/Web.config::service_endpoint::CRM_Endpoint
 */
final class CRMEndpointAction
{
    /**
     * Legacy WCF binding address for the Dynamics CRM Organization service.
     *
     * @var string
     */
    private const ENDPOINT_ADDRESS = 'http://manjula.idir.bcgov:8080/EQATEST/XRMServices/2011/Organization.svc';

    /**
     * Execute a SOAP/REST call against the CRM Organization service.
     *
     * @param  string  $operation  Name of the remote operation to invoke.
     * @param  array<string, mixed>  $payload  Request payload for the operation.
     * @return array<string, mixed> Decoded response body.
     *
     * @throws RuntimeException When the endpoint call fails or is misconfigured.
     */
    public function __invoke(string $operation, array $payload = []): array
    {
        $endpoint = config('services.crm_endpoint.address', self::ENDPOINT_ADDRESS);

        if (empty($endpoint)) {
            throw new RuntimeException('CRM_Endpoint address is not configured.');
        }

        /** @var Response $response */
        $response = Http::acceptJson()
            ->timeout(30)
            ->baseUrl($endpoint)
            ->post($operation, $payload);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'CRM_Endpoint call to operation "%s" failed with status %d.',
                $operation,
                $response->status()
            ));
        }

        return $response->json() ?? [];
    }
}