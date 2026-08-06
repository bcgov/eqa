<?php

declare(strict_types=1);

namespace Modules\Web\Actions;

use Illuminate\Http\RedirectResponse;

/**
 * Redirects the current session to the external SiteMinder logoff endpoint.
 *
 * Source lineage: EQA WEB/Web.config::service_endpoint::SiteMinderLogoffRedirect
 * External address: https://logon.gov.bc.ca/clp-cgi/logoff.cgi
 */
final class SiteMinderLogoffRedirectAction
{
    /**
     * The external SiteMinder logoff endpoint address.
     */
    private const LOGOFF_ENDPOINT = 'https://logon.gov.bc.ca/clp-cgi/logoff.cgi';

    public function __invoke(): RedirectResponse
    {
        return redirect()->away(self::LOGOFF_ENDPOINT);
    }
}