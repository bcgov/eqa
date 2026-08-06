<?php

namespace Modules\Web\Actions;

/**
 * Provides the list of externally allowed domains for the AllowedDomains
 * service endpoint, sourced from EQA WEB/Web.config::service_endpoint::AllowedDomains.
 *
 * Source lineage:
 * - source_ref: EQA WEB/Web.config::service_endpoint::AllowedDomains
 * - connector: external_refs
 * - kind: service_endpoint
 */
class AllowedDomainsAction
{
    /**
     * @var array<int, string>
     */
    private const ALLOWED_DOMAINS = [
        'https://logon.gov.bc.ca',
        'https://admin.privatetraininginstitutions.gov.bc.ca',
    ];

    /**
     * Execute the action and return the configured allowed domains.
     *
     * @return array<int, string>
     */
    public function __invoke(): array
    {
        return self::ALLOWED_DOMAINS;
    }
}