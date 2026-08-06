<?php

namespace Modules\Api\Actions;

/**
 * Provides the BCEP (Beanstream) Payment URL external service endpoint.
 *
 * Source lineage: EQA API/Web.Debug.config::service_endpoint::BCEP_Payment_URL
 */
class BCEPPaymentURLAction
{
    /**
     * The external service endpoint address.
     */
    private const ENDPOINT_ADDRESS = 'https://www.beanstream.com/scripts/Payment/Payment.asp';

    /**
     * Resolve the BCEP Payment URL external service endpoint address.
     *
     * @return string
     */
    public function __invoke(): string
    {
        return config('services.bcep_payment_url.address', self::ENDPOINT_ADDRESS);
    }
}