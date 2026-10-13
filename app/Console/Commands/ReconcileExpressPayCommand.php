<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ExpressPayService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Settles Express Pay transactions whose payer never returned to the receipt
 * page (closed the browser, lost the session). Only expired ones are queried:
 * before hashExpiry a "not found" result may still turn into a payment.
 */
class ReconcileExpressPayCommand extends Command
{
    protected $signature = 'eqa:reconcile-payments';

    protected $description = 'Confirm the result of expired pending BC Express Pay transactions with Worldline.';

    public function handle(ExpressPayService $expressPay): int
    {
        if (! $expressPay->isEnabled()) {
            $this->info('Express Pay is not configured; nothing to reconcile.');

            return self::SUCCESS;
        }

        $counts = [];
        DB::table('payment_transactions')
            ->where('status', ExpressPayService::PENDING)
            ->where('hash_expires_at', '<=', Carbon::now())
            ->lazyById()
            ->each(function (object $transaction) use ($expressPay, &$counts): void {
                $status = $expressPay->reconcile($transaction);
                $counts[$status] = ($counts[$status] ?? 0) + 1;
            });

        $summary = $counts === []
            ? 'none'
            : implode(', ', array_map(fn (string $s, int $n): string => "{$s}: {$n}", array_keys($counts), $counts));
        $this->info("Express Pay transactions reconciled ({$summary}).");

        return self::SUCCESS;
    }
}
