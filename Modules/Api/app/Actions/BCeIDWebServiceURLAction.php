<?php

declare(strict_types=1);

namespace Modules\Api\Actions;

/**
 * Provides the external BCeID Web Service SOAP endpoint URL.
 *
 * Source lineage: EQA API/Web.config::service_endpoint::BCeID_WebService_URL
 */
final class BCeIDWebServiceURLAction
{
    /**
     * Address of the BCeID Web Service V9 endpoint as defined in the legacy Web.config.
     */
    private const SERVICE_ENDPOINT_ADDRESS = 'https://gws1.test.bceid.ca/webservices/Client/V9/BCeIDService.asmx';

    public function __invoke(): string
    {
        return config('services.bceid.web_service_url', self::SERVICE_ENDPOINT_ADDRESS);
    }
}