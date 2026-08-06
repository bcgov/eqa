<?php

declare(strict_types=1);

namespace Modules\Web\Actions;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Invokes the external SiteMinder logoff endpoint to terminate the
 * user's single sign-on session with the government SiteMinder Policy Server.
 *
 * Source lineage: EQA WEB/Web.config::service_endpoint::SiteMinderLogoffScript
 */
final class SiteMinderLogoffScriptAction
{
    /**
     * The external SiteMinder logoff CGI endpoint address.
     */
    private const ENDPOINT = 'https://logon.gov.bc.ca/clp-cgi/logoff.cgi';

    public function __invoke(): Response
    {
        $response = Http::timeout(10)
            ->connectTimeout(5)
            ->get(self::ENDPOINT);

        if ($response->failed()) {
            throw new RuntimeException(
                sprintf(
                    'SiteMinder logoff request to "%s" failed with status %d.',
                    self::ENDPOINT,
                    $response->status()
                )
            );
        }

        return $response;
    }
}