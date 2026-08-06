<?php

declare(strict_types=1);

namespace Modules\Web\Actions;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Invokable action encapsulating the external CRM organization service endpoint.
 *
 * Source lineage: EQA WEB/Web.config::service_endpoint::CRM_Endpoint
 */
final class CRMEndpointAction
{
    /**
     * Endpoint address migrated verbatim from Web.config.
     *
     * @var string
     */
    private const ENDPOINT_ADDRESS = 'http://142.34.167.119:8080/EQADEV/XRMServices/2011/Organization.svc';

    /**
     * Invoke the CRM organization service endpoint.
     *
     * @param string $method  HTTP method to use for the request.
     * @param array<string, mixed> $payload  Optional request payload/body.
     * @param array<string, string> $headers  Optional additional HTTP headers.
     *
     * @return Response
     *
     * @throws RuntimeException When the request to the external CRM endpoint fails.
     */
    public function __invoke(string $method = 'GET', array $payload = [], array $headers = []): Response
    {
        try {
            $response = Http::withHeaders($headers)
                ->send($method, self::ENDPOINT_ADDRESS, [
                    'json' => $payload,
                ]);

            return $response;
        } catch (Exception $exception) {
            throw new RuntimeException(
                sprintf('Failed to invoke CRM endpoint [%s]: %s', self::ENDPOINT_ADDRESS, $exception->getMessage()),
                0,
                $exception
            );
        }
    }

    /**
     * Get the configured endpoint address.
     *
     * @return string
     */
    public function getEndpointAddress(): string
    {
        return self::ENDPOINT_ADDRESS;
    }
}