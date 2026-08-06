<?php

namespace Modules\Api\Actions;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Invokable action for calling the legacy NAV 2013 R2 OData service endpoint
 * ("NAV_OData_Endpoint") that was declared as an external service reference
 * in `EQA API/Web.Debug.config`.
 *
 * Source lineage: EQA API/Web.Debug.config::service_endpoint::NAV_OData_Endpoint
 */
class NAVODataEndpointAction
{
    /**
     * Fallback base address migrated verbatim from the legacy
     * Web.Debug.config `<client>` endpoint entry. Production code should
     * override this via the `services.nav_odata.address` config key.
     */
    private const DEFAULT_ADDRESS = "http://manjula:7048/NAV2013R2_DEV/OData/Company('PCTIA-DEV')";

    public function __construct(
        private readonly ?string $address = null,
        private readonly ?string $username = null,
        private readonly ?string $password = null,
    ) {
    }

    /**
     * Call an OData resource on the NAV_OData_Endpoint service.
     *
     * @param string $resource OData resource/entity set, e.g. "Customers".
     * @param array<string, mixed> $query Optional OData query string parameters
     *                                    (e.g. $filter, $top, $select).
     * @param string $method HTTP verb to use for the call.
     * @param array<string, mixed>|null $payload Request body for write operations.
     *
     * @return Response
     *
     * @throws RuntimeException When the endpoint address is not configured.
     */
    public function __invoke(
        string $resource,
        array $query = [],
        string $method = 'get',
        ?array $payload = null,
    ): Response {
        $baseAddress = $this->resolveAddress();

        if ($baseAddress === '') {
            throw new RuntimeException(
                'NAV_OData_Endpoint address is not configured. Set services.nav_odata.address.'
            );
        }

        $url = rtrim($baseAddress, '/').'/'.ltrim($resource, '/');

        $request = $this->buildRequest();

        return match (strtolower($method)) {
            'post' => $request->post($url, $payload ?? []),
            'put' => $request->put($url, $payload ?? []),
            'patch' => $request->patch($url, $payload ?? []),
            'delete' => $request->delete($url, $payload ?? []),
            default => $request->get($url, $query),
        };
    }

    private function buildRequest(): PendingRequest
    {
        $request = Http::acceptJson();

        $username = $this->username ?? config('services.nav_odata.username');
        $password = $this->password ?? config('services.nav_odata.password');

        if (!empty($username)) {
            $request = $request->withBasicAuth((string) $username, (string) $password);
        }

        return $request;
    }

    private function resolveAddress(): string
    {
        return $this->address
            ?? config('services.nav_odata.address', self::DEFAULT_ADDRESS)
            ?? '';
    }
}