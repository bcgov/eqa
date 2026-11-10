<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Admin\Services\ProcessNotifier;

/**
 * Reproduces the legacy Dynamics scheduled workflows for time-based EQA
 * notifications:
 *  - "EQA Reapplication Required" renewal reminders (designation expiring soon)
 *  - "PTIB Certificate Expiry" reminders (Private Training pathway)
 *
 * Reminders fire when an expiry date lands exactly on today + one of the day
 * offsets (defaults: 90 and 30 days), which naturally sends each reminder once.
 * Everything is funnelled through the toggle-gated EmailNotificationService, so
 * nothing goes out while the master switch is OFF (the default).
 */
class SendScheduledNotificationsCommand extends Command
{
    protected $signature = 'eqa:send-notifications
        {--days=* : Day offsets before expiry to notify on (default: 90 and 30)}';

    protected $description = 'Send time-based EQA notifications (renewal + PTIB certificate expiry reminders).';

    public function handle(ProcessNotifier $notifier): int
    {
        $offsets = array_map('intval', (array) $this->option('days'));
        $offsets = array_values(array_filter($offsets, static fn (int $d): bool => $d >= 0));
        if ($offsets === []) {
            $offsets = [90, 30];
        }

        $renewals = $notifier->sendRenewalReminders($offsets);
        $ptib = $notifier->sendPtibCertExpiryReminders($offsets);

        $this->info(sprintf(
            'EQA reminders processed (offsets: %s days) — reapplication required: %d, PTIB certificate expiry: %d.',
            implode(', ', $offsets),
            $renewals,
            $ptib,
        ));

        return self::SUCCESS;
    }
}
