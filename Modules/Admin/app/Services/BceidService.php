<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Calls the BCeID SOAP Web Service (V10) `searchBCeIDAccount` operation to
 * resolve a BCeID username into its User GUID, Business GUID and Business Legal
 * Name. This mirrors the legacy Dynamics plugin
 * CGI.Plugins.BCeIDRetrieve.VerifyBCeID: same online service id, requester guid,
 * Business requester account type, Ascending/UserId sort and Exact userId match.
 */
class BceidService
{
    private const SOAP_ACTION = 'http://www.bceid.ca/webservices/Client/V10/searchBCeIDAccount';

    /**
     * Look up a single BCeID account by its exact username.
     *
     * @return array{found: bool, message: string, user_guid: ?string, business_guid: ?string, business_legal_name: ?string, first_name: ?string, last_name: ?string, email: ?string}
     */
    public function search(string $username): array
    {
        $username = trim($username);
        if ($username === '') {
            throw new RuntimeException('No BCeID username on this contact to look up.');
        }

        $config = config('bceid');
        foreach (['url', 'username', 'password', 'online_service_id', 'requester_user_guid'] as $key) {
            if (empty($config[$key])) {
                throw new RuntimeException("BCeID integration is not configured ({$key} missing).");
            }
        }

        $body = $this->buildEnvelope($username, $config);
        $response = $this->post($body, $config);

        return $this->parse($response);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function buildEnvelope(string $username, array $config): string
    {
        $osid = htmlspecialchars((string) $config['online_service_id'], ENT_XML1);
        $guid = htmlspecialchars((string) $config['requester_user_guid'], ENT_XML1);
        $user = htmlspecialchars($username, ENT_XML1);

        return <<<XML
        <?xml version="1.0" encoding="utf-8"?>
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:v10="http://www.bceid.ca/webservices/Client/V10/">
          <soap:Body>
            <v10:searchBCeIDAccount>
              <v10:bceidAccountSearchRequest>
                <v10:onlineServiceId>{$osid}</v10:onlineServiceId>
                <v10:requesterAccountTypeCode>Business</v10:requesterAccountTypeCode>
                <v10:requesterUserGuid>{$guid}</v10:requesterUserGuid>
                <v10:pagination><v10:pageSizeMaximum>500</v10:pageSizeMaximum><v10:pageIndex>1</v10:pageIndex></v10:pagination>
                <v10:sort><v10:direction>Ascending</v10:direction><v10:onProperty>UserId</v10:onProperty></v10:sort>
                <v10:accountMatch>
                  <v10:userId><v10:value>{$user}</v10:value><v10:matchPropertyUsing>Exact</v10:matchPropertyUsing></v10:userId>
                  <v10:searchableAccountType>Business</v10:searchableAccountType>
                </v10:accountMatch>
              </v10:bceidAccountSearchRequest>
            </v10:searchBCeIDAccount>
          </soap:Body>
        </soap:Envelope>
        XML;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function post(string $body, array $config): string
    {
        $ch = curl_init((string) $config['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: '.self::SOAP_ACTION,
            ],
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $config['domain']."\\".$config['username'].':'.$config['password'],
            CURLOPT_TIMEOUT => (int) $config['timeout'],
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("Could not reach the BCeID web service: {$error}");
        }
        if ($status === 401) {
            throw new RuntimeException('BCeID rejected the service credentials (HTTP 401).');
        }
        if ($status !== 200) {
            throw new RuntimeException("BCeID web service returned HTTP {$status}.");
        }

        return (string) $response;
    }

    /**
     * @return array{found: bool, message: string, user_guid: ?string, business_guid: ?string, business_legal_name: ?string, first_name: ?string, last_name: ?string, email: ?string}
     */
    private function parse(string $response): array
    {
        // Strip namespace declarations and element prefixes so we can navigate
        // the (heavily namespaced) response with plain SimpleXML paths.
        $clean = preg_replace('/xmlns(:\w+)?="[^"]*"/', '', $response) ?? $response;
        $clean = preg_replace('/<(\/?)[\w.]+:/', '<$1', $clean) ?? $clean;

        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($clean);
        libxml_use_internal_errors($prev);

        if ($xml === false) {
            throw new RuntimeException('BCeID returned an unreadable response.');
        }

        $result = $xml->Body->searchBCeIDAccountResponse->searchBCeIDAccountResult ?? null;
        if ($result === null) {
            throw new RuntimeException('BCeID returned an unexpected response.');
        }

        $code = (string) $result->code;
        if (strcasecmp($code, 'Success') !== 0) {
            $reason = trim((string) $result->failureCode.' '.(string) $result->message);
            Log::warning('BCeID search failed', ['code' => $code, 'reason' => $reason]);

            throw new RuntimeException($reason !== '' ? $reason : "BCeID search failed ({$code}).");
        }

        $account = $result->accountList->BCeIDAccount ?? null;
        if ($account === null) {
            return [
                'found' => false,
                'message' => 'No matching BCeID account was found.',
                'user_guid' => null,
                'business_guid' => null,
                'business_legal_name' => null,
                'first_name' => null,
                'last_name' => null,
                'email' => null,
            ];
        }

        $value = static function ($node): ?string {
            $v = trim((string) ($node->value ?? ''));

            return $v === '' ? null : $v;
        };

        return [
            'found' => true,
            'message' => 'BCeID account matched.',
            'user_guid' => $value($account->guid),
            'business_guid' => $value($account->business->guid ?? null),
            'business_legal_name' => $value($account->business->legalName ?? null),
            'first_name' => $value($account->individualIdentity->name->firstname ?? null),
            'last_name' => $value($account->individualIdentity->name->surname ?? null),
            'email' => $value($account->contact->email ?? null),
        ];
    }
}
