<?php

namespace Modules\Web\Actions;

/**
 * Resolves the API_Base_URL service endpoint originally defined in
 * EQA WEB/Web.config::service_endpoint::API_Base_URL.
 *
 * Source lineage: EQA WEB/Web.config (external_refs::service_endpoint::API_Base_URL)
 */
class APIBaseURLAction
{
    /**
     * Default endpoint address migrated from the legacy Web.config service_endpoint.
     */
    private const DEFAULT_ADDRESS = 'http://manjula.idir.bcgov:8082';

    /**
     * Resolve the API base URL, preferring configuration over the legacy default.
     */
    public function __invoke(): string
    {
        return config('services.eqa_web.api_base_url', self::DEFAULT_ADDRESS);
    }
}