<?php

declare(strict_types=1);

namespace Modules\Web\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Retrieves the embeddable EQA seal markup/reference for a given CRM record
 * by calling the legacy EqaEmbedSeal external service endpoint.
 *
 * Source lineage:
 * - Connector: external_refs
 * - Kind: service_endpoint
 * - Ref: EQA WEB/Web.config::service_endpoint::EqaEmbedSeal
 * - Original address: https://admin.bceqa.gov.bc.ca/apply/VerifySeal?crmId=
 */
final class EqaEmbedSealAction
{
    /**
     * Base address of the legacy EqaEmbedSeal service endpoint.
     */
    private const DEFAULT_ENDPOINT = 'https://admin.bceqa.gov.bc.ca/apply/VerifySeal';

    /**
     * Invoke the action to fetch the embeddable seal for the given CRM id.
     *
     * @param string $crmId The CRM record identifier the seal should be verified/embedded for.
     *
     * @return string The raw response body (typically HTML/markup) returned by the seal service.
     *
     * @throws RuntimeException If the CRM id is empty or the upstream service call fails.
     */
    public function __invoke(string $crmId): string
    {
        $crmId = trim($crmId);

        if ($crmId === '') {
            throw new RuntimeException('EqaEmbedSealAction requires a non-empty crmId.');
        }

        $endpoint = config('services.eqa.embed_seal_endpoint', self::DEFAULT_ENDPOINT);

        try {
            $response = Http::acceptJson()
                ->timeout(15)
                ->get($endpoint, [
                    'crmId' => $crmId,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                sprintf('Unable to connect to EqaEmbedSeal service for crmId "%s".', $crmId),
                previous: $exception,
            );
        }

        try {
            $response->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException(
                sprintf('EqaEmbedSeal service returned an error for crmId "%s".', $crmId),
                previous: $exception,
            );
        }

        return $response->body();
    }
}