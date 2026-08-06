<create>
<parameter name="path">C:\Users\KMARSH\AppData\Local\Temp\ai-bridge-45dce1497f13\BCEPResultURLAction.php</parameter>
<parameter name="file_text"><?php

namespace Modules\Api\Actions;

/**
 * Resolves the BCEP (Beanstream) result/process-transaction endpoint URL.
 *
 * Migrated from the legacy external service endpoint reference:
 * EQA API/Web.Debug.config::service_endpoint::BCEP_Result_URL
 */
class BCEPResultURLAction
{
    /**
     * Fallback endpoint address sourced from the legacy Web.Debug.config
     * service_endpoint definition for BCEP_Result_URL.
     */
    private const DEFAULT_ADDRESS = 'https://www.beanstream.com/scripts/process_transaction.asp';

    /**
     * Resolve the BCEP result URL, allowing environment/config overrides.
     */
    public function __invoke(): string
    {
        return (string) config('services.bcep.result_url', self::DEFAULT_ADDRESS);
    }
}
</parameter>
</create>