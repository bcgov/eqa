<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Time-based EQA notifications (renewal + PTIB certificate expiry reminders),
// migrated from the legacy Dynamics scheduled workflows. Gated by the global
// email master switch, so it is a safe no-op until sending is enabled.
Schedule::command('eqa:send-notifications')
    ->dailyAt('07:00')
    ->timezone('America/Vancouver')
    ->withoutOverlapping();

// Settle BC Express Pay payments whose payer never came back to the receipt page.
Schedule::command('eqa:reconcile-payments')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// Expire applications past their admin fee / change request / appeal fee deadline.
Schedule::command('eqa:enforce-deadlines')
    ->dailyAt('01:00')
    ->timezone('America/Vancouver')
    ->withoutOverlapping();
