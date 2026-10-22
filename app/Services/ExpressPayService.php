<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Admin\Services\ApplicationFeeService;
use Psr\Log\LoggerInterface;

/**
 * BC Express Pay single payments (trnType P) and transaction queries (trnType Q)
 * through the PCIR gateway. Card details are only ever entered on the Worldline
 * hosted page; this app sees the order number and the processing result.
 */
class ExpressPayService
{
    /** Keeps order numbers clear of Worldline transaction IDs, which start at 10,000,000. */
    public const ORDER_NUMBER_BASE = 500_000_000;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const DECLINED = 'declined';

    public const CANCELLED = 'cancelled';

    public const ABANDONED = 'abandoned';

    public const REVIEW = 'review';

    public function isEnabled(): bool
    {
        return filter_var(config('services.expresspay.enabled'), FILTER_VALIDATE_BOOL)
            && filled(config('services.expresspay.merchant_id'))
            && filled(config('services.expresspay.hash_key'));
    }

    public function amountDue(object $invoice): float
    {
        $due = $invoice->invoice_balance ?? $invoice->total_charges ?? $invoice->invoice_amount ?? 0;

        return round(max(0.0, (float) $due), 2);
    }

    public function isPayable(object $invoice): bool
    {
        return ! in_array($invoice->invoice_status ?? null, ['Paid', 'Expired'], true)
            && $this->amountDue($invoice) > 0
            && ! $this->hasPaymentUnderReview((string) ($invoice->crm_id ?? ''));
    }

    /** Worldline may have taken this payment but the app could not confirm it: never invite the payer to pay again. */
    public function hasPaymentUnderReview(string $invoiceCrmId): bool
    {
        return DB::table('payment_transactions')
            ->where('invoice_crm_id', $invoiceCrmId)
            ->where('status', self::REVIEW)
            ->exists();
    }

    /** Ministry staff impersonating an institution must not pay on its behalf. */
    public function canUserPay(?User $user, bool $impersonating): bool
    {
        return $this->isEnabled()
            && $user !== null
            && $user->hasAnyRole(Role::INSTITUTION_ROLES)
            && ! $impersonating;
    }

    /**
     * Record a pending transaction for the invoice's outstanding balance.
     *
     * @return array{order_number: int, url: string} the signed hosted-payment-page URL to redirect the payer to
     */
    public function begin(object $invoice, string $receiptUrl, ?int $userId): array
    {
        $amount = $this->amountDue($invoice);
        $expiresAt = Carbon::now()->addMinutes((int) config('services.expresspay.hash_expiry_minutes') ?: 30);

        $orderNumber = DB::transaction(function () use ($invoice, $amount, $expiresAt, $userId): int {
            $id = DB::table('payment_transactions')->insertGetId([
                'invoice_crm_id' => $invoice->crm_id,
                'application_crm_id' => $invoice->application_crm_id ?? null,
                'institution_crm_id' => $invoice->institution_crm_id ?? null,
                'user_id' => $userId,
                'trn_type' => 'P',
                'amount' => $amount,
                'status' => self::PENDING,
                'hash_expires_at' => $expiresAt,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            $orderNumber = ((int) config('services.expresspay.order_number_base') ?: self::ORDER_NUMBER_BASE) + (int) $id;
            if ($orderNumber > 9_999_999_999) {
                throw new \RuntimeException('Express Pay order numbers must stay within 10 digits; lower EXPRESS_PAY_ORDER_NUMBER_BASE.');
            }
            DB::table('payment_transactions')->where('id', $id)->update(['order_number' => $orderNumber]);

            return $orderNumber;
        });

        $query = $this->sign([
            'merchant_id' => (string) config('services.expresspay.merchant_id'),
            'trnType' => 'P',
            'trnOrderNumber' => (string) $orderNumber,
            'ref1' => $receiptUrl,
            'trnAmount' => number_format($amount, 2, '.', ''),
            'hashExpiry' => $expiresAt->copy()->setTimezone('America/Vancouver')->format('YmdHi'),
        ]);

        return [
            'order_number' => $orderNumber,
            'url' => config('services.expresspay.payment_url').'?'.$query,
        ];
    }

    /**
     * Ask Worldline for the result of a pending transaction and settle it. Returns the resulting status.
     * A hash-verified receipt redirect for the same order is used when the lookup is unavailable or
     * ambiguous (760), or when it reports an approval the lookup does not.
     *
     * @param  array<string, mixed>|null  $signedRedirect  output of verifiedRedirect()
     */
    public function reconcile(object $transaction, ?array $signedRedirect = null): string
    {
        if ($transaction->status !== self::PENDING) {
            return $transaction->status;
        }

        $response = $this->query((string) $transaction->order_number);

        $redirect = $signedRedirect !== null
            && (string) ($signedRedirect['trnOrderNumber'] ?? '') === (string) $transaction->order_number
                ? $signedRedirect
                : null;
        $ambiguous = $response === null || (string) ($response['messageId'] ?? '') === '760';
        $redirectOnlyApproval = ! $ambiguous
            && (string) ($response['trnApproved'] ?? '') !== '1'
            && (string) ($redirect['trnApproved'] ?? '') === '1';

        if ($redirect !== null && ($ambiguous || $redirectOnlyApproval)) {
            if ($redirectOnlyApproval) {
                $this->log()->warning('Express Pay query did not report the approval in the signed receipt redirect; using the redirect.', [
                    'order_number' => $transaction->order_number,
                    'query' => $response !== null ? self::loggable($response) : null,
                ]);
            }
            $response = $redirect;
        }

        return $response === null ? self::PENDING : $this->settle($transaction, $response);
    }

    /**
     * The transaction response Worldline appends to the receipt redirect, but only if its hashValue
     * (MD5 of the text before "&hashValue=" followed by the hash key) proves it was not altered.
     *
     * @return array<string, mixed>|null
     */
    public function verifiedRedirect(string $rawQuery): ?array
    {
        $pos = strpos($rawQuery, '&hashValue=');
        if ($pos === false) {
            return null;
        }

        $signed = substr($rawQuery, 0, $pos);
        $given = strtolower(explode('&', substr($rawQuery, $pos + 11))[0]);
        if (! hash_equals(md5($signed.config('services.expresspay.hash_key')), $given)) {
            $this->log()->warning('Express Pay receipt redirect failed hash validation.');

            return null;
        }

        parse_str($signed, $parsed);

        return $parsed;
    }

    /** Statuses EQA staff may override after finding the payment in the Worldline report. */
    public const STAFF_ACCEPTABLE = [self::REVIEW, self::DECLINED, self::ABANDONED, self::CANCELLED];

    /** EQA staff found the payment for an unconfirmed transaction in the Worldline report. */
    public function acceptUnconfirmed(object $transaction, string $trnId, ?string $authCode): bool
    {
        $accepted = DB::transaction(function () use ($transaction, $trnId, $authCode): bool {
            $current = DB::table('payment_transactions')->where('id', $transaction->id)->lockForUpdate()->first();
            if ($current === null || ! in_array($current->status, self::STAFF_ACCEPTABLE, true)) {
                return false;
            }

            DB::table('payment_transactions')->where('id', $current->id)->update([
                'status' => self::APPROVED,
                'trn_id' => $trnId,
                'auth_code' => $authCode,
                'message_text' => 'Accepted by EQA staff from the Worldline report',
                'updated_at' => Carbon::now(),
            ]);
            $this->applyToInvoice($current, ['trnId' => $trnId, 'authCode' => $authCode]);

            return true;
        });

        if ($accepted) {
            app(ApplicationFeeService::class)->invoicePaid((string) $transaction->invoice_crm_id);
        }

        return $accepted;
    }

    /**
     * Should an earlier pending attempt block a new payment for the same invoice? Only while the
     * payer could still complete it: not when Worldline says it was never processed, and never
     * past its hashExpiry (an unconfirmed attempt stays pending for eqa:reconcile-payments).
     */
    public function stillInProgress(object $transaction): bool
    {
        if ($transaction->status !== self::PENDING) {
            return false;
        }

        $response = $this->query((string) $transaction->order_number);
        if ($response !== null) {
            if ($this->settle($transaction, $response) !== self::PENDING) {
                return false;
            }
            if ((string) ($response['messageId'] ?? '') === '559') {
                return false;
            }
        }

        return Carbon::now()->lessThan(Carbon::parse($transaction->hash_expires_at));
    }

    /**
     * Server-to-server transaction query by order number.
     *
     * @return array<string, mixed>|null null when the gateway could not be reached
     */
    public function query(string $orderNumber): ?array
    {
        $query = $this->sign([
            'requestType' => 'BACKEND',
            // PCIR only accepts merchant_id on a GET query string (the guides' "merchantid" + POST return 500).
            'merchant_id' => (string) config('services.expresspay.merchant_id'),
            'trnType' => 'Q',
            'trnOrderNumber' => $orderNumber,
        ]);

        try {
            $response = Http::timeout(30)->get(config('services.expresspay.query_url').'?'.$query);
        } catch (ConnectionException $e) {
            $this->log()->warning('Express Pay query could not reach the gateway.', ['order_number' => $orderNumber, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            $this->log()->warning('Express Pay query failed.', [
                'order_number' => $orderNumber,
                'status' => $response->status(),
                'body' => mb_substr(strip_tags($response->body()), 0, 500),
            ]);

            return null;
        }

        parse_str(trim($response->body()), $parsed);

        $this->log()->info('Express Pay query response.', [
            'order_number' => $orderNumber,
            'query_url' => config('services.expresspay.query_url'),
            'http_status' => $response->status(),
            'response' => self::loggable($parsed),
        ]);

        return $parsed;
    }

    /**
     * Gateway response fields that are safe to log (whitelist; never hashes or card data).
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public static function loggable(array $response): array
    {
        return array_intersect_key($response, array_flip([
            'trnApproved', 'trnId', 'trnOrderNumber', 'trnAmount', 'trnType', 'trnDate',
            'messageId', 'messageText', 'authCode', 'cardType', 'paymentMethod', 'errorType', 'errorFields', 'responseType',
        ]));
    }

    private function log(): LoggerInterface
    {
        return Log::channel('expresspay');
    }

    /**
     * Apply a Worldline response to a pending transaction (and its invoice when approved).
     *
     * @param  array<string, mixed>  $response
     */
    public function settle(object $transaction, array $response): string
    {
        $status = $this->outcome($response, Carbon::parse($transaction->hash_expires_at));

        if ($status === self::APPROVED && abs((float) ($response['trnAmount'] ?? 0) - (float) $transaction->amount) >= 0.01) {
            $this->log()->error('Express Pay approved amount does not match the transaction.', [
                'order_number' => $transaction->order_number,
                'expected' => $transaction->amount,
                'trnAmount' => $response['trnAmount'] ?? null,
            ]);
            $status = self::REVIEW;
        }

        if ($status === self::PENDING) {
            return self::PENDING;
        }

        $settledNow = false;
        $result = DB::transaction(function () use ($transaction, $response, $status, &$settledNow): string {
            $current = DB::table('payment_transactions')->where('id', $transaction->id)->lockForUpdate()->first();

            // The receipt page and the reconcile job can race; only the first one settles.
            if ($current === null || $current->status !== self::PENDING) {
                return $current?->status ?? $status;
            }

            DB::table('payment_transactions')->where('id', $current->id)->update([
                'status' => $status,
                'trn_id' => $response['trnId'] ?? null,
                'auth_code' => $response['authCode'] ?? null,
                'message_id' => $response['messageId'] ?? null,
                'message_text' => isset($response['messageText']) ? mb_substr((string) $response['messageText'], 0, 255) : null,
                'card_type' => isset($response['cardType']) ? mb_substr((string) $response['cardType'], 0, 4) : null,
                'trn_date' => $response['trnDate'] ?? null,
                'response' => json_encode($response),
                'completed_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            if ($status === self::APPROVED) {
                $this->applyToInvoice($current, $response);
            }
            $settledNow = true;

            return $status;
        });

        // Workflow side effects (status changes, emails) run once, after the payment is committed.
        if ($settledNow && $result === self::APPROVED) {
            app(ApplicationFeeService::class)->invoicePaid((string) $transaction->invoice_crm_id);
        }

        $this->log()->info('Express Pay transaction settled.', [
            'order_number' => $transaction->order_number,
            'invoice_crm_id' => $transaction->invoice_crm_id,
            'status' => $result,
            'settled_now' => $settledNow,
            'response' => self::loggable($response),
        ]);

        return $result;
    }

    /**
     * Map a Worldline response to a transaction status.
     *
     * @param  array<string, mixed>  $response
     */
    public function outcome(array $response, CarbonInterface $hashExpiresAt): string
    {
        // A system/user error (e.g. bad hash) says nothing about the payment itself.
        if (! array_key_exists('trnApproved', $response) || in_array($response['errorType'] ?? 'N', ['S', 'U'], true)) {
            $this->log()->warning('Express Pay returned an error response.', [
                'errorType' => $response['errorType'] ?? null,
                'messageText' => $response['messageText'] ?? null,
            ]);

            return self::PENDING;
        }

        if ((string) $response['trnApproved'] === '1') {
            return self::APPROVED;
        }

        if ((string) ($response['messageId'] ?? '') === '760') {
            $this->log()->warning('Express Pay found more than one transaction for this order number; check the order number base per environment.', [
                'trnOrderNumber' => $response['trnOrderNumber'] ?? null,
                'messageText' => $response['messageText'] ?? null,
            ]);

            return self::REVIEW;
        }

        return match ((string) ($response['messageId'] ?? '')) {
            // Not processed yet; after hashExpiry it never will be.
            '559' => Carbon::now()->greaterThan($hashExpiresAt) ? self::ABANDONED : self::PENDING,
            '761', '766' => self::CANCELLED,
            default => $this->declinedOrUnrecognised($response),
        };
    }

    /**
     * A decline is a real gateway transaction (it has a trnId); anything else is a lookup problem
     * that must not be recorded as a failed payment.
     *
     * @param  array<string, mixed>  $response
     */
    private function declinedOrUnrecognised(array $response): string
    {
        if (! in_array((string) ($response['trnId'] ?? ''), ['', '0'], true)) {
            return self::DECLINED;
        }

        $this->log()->warning('Express Pay query returned an unrecognised response; leaving the transaction pending.', [
            'trnOrderNumber' => $response['trnOrderNumber'] ?? null,
            'messageId' => $response['messageId'] ?? null,
            'messageText' => $response['messageText'] ?? null,
        ]);

        return self::PENDING;
    }

    /**
     * Append hashValue: MD5 of the exact query string followed by the shared hash key.
     *
     * @param  array<string, string>  $params
     */
    public function sign(array $params): string
    {
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC1738);

        return $query.'&hashValue='.strtoupper(md5($query.config('services.expresspay.hash_key')));
    }

    /** @param  array<string, mixed>  $response */
    private function applyToInvoice(object $transaction, array $response): void
    {
        $invoice = DB::table('invoices')->where('crm_id', $transaction->invoice_crm_id)->lockForUpdate()->first();
        if ($invoice === null) {
            $this->log()->error('Express Pay approved a payment for a missing invoice.', ['order_number' => $transaction->order_number]);

            return;
        }

        $due = $this->amountDue($invoice);
        if ($due <= 0) {
            $this->log()->warning('Express Pay payment received for an invoice with no balance; refund may be needed.', [
                'order_number' => $transaction->order_number,
                'invoice_crm_id' => $invoice->crm_id,
            ]);
        }

        $balance = round(max(0.0, $due - (float) $transaction->amount), 2);
        $updates = [
            'invoice_balance' => $balance,
            'payment_amount' => round((float) ($invoice->payment_amount ?? 0) + (float) $transaction->amount, 2),
            'payment_received_date' => Carbon::now('America/Vancouver')->toDateString(),
            'payment_method' => 'Express Pay',
            'transaction_reference' => $response['trnId'] ?? null,
            'transaction_authorization' => $response['authCode'] ?? null,
        ];
        if ($balance <= 0) {
            $updates['invoice_status'] = 'Paid';
        }

        DB::table('invoices')->where('crm_id', $invoice->crm_id)->update($updates);
    }
}
