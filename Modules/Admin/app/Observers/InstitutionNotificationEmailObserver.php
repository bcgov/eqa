<?php

namespace Modules\Admin\Observers;

use Modules\Admin\Models\Institution;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Observer migrated from legacy CRM plugin:
 * Plugins/CGI.Plugins/Notification Email/InstitutionNotificationEmail.cs
 *
 * Sends a notification email when an Institution record changes state,
 * replicating the plugin's use of the CRM organization service to trigger
 * notification emails.
 */
class InstitutionNotificationEmailObserver
{
    /**
     * Handle the Institution "created" event.
     */
    public function created(Institution $institution): void
    {
        $this->sendNotificationEmail($institution, 'created');
    }

    /**
     * Handle the Institution "updated" event.
     */
    public function updated(Institution $institution): void
    {
        if (! $institution->wasChanged()) {
            return;
        }

        $this->sendNotificationEmail($institution, 'updated');
    }

    /**
     * Handle the Institution "deleted" event.
     */
    public function deleted(Institution $institution): void
    {
        $this->sendNotificationEmail($institution, 'deleted');
    }

    /**
     * Handle the Institution "restored" event.
     */
    public function restored(Institution $institution): void
    {
        $this->sendNotificationEmail($institution, 'restored');
    }

    /**
     * Handle the Institution "force deleted" event.
     */
    public function forceDeleted(Institution $institution): void
    {
        $this->sendNotificationEmail($institution, 'force deleted');
    }

    /**
     * Build and dispatch the institution notification email.
     */
    protected function sendNotificationEmail(Institution $institution, string $event): void
    {
        $recipient = $institution->notification_email ?? null;

        if (empty($recipient)) {
            Log::warning('InstitutionNotificationEmailObserver: no recipient email found for institution.', [
                'institution_id' => $institution->getKey(),
                'event' => $event,
            ]);

            return;
        }

        try {
            Mail::raw(
                sprintf(
                    'The institution "%s" has been %s.',
                    $institution->name ?? $institution->getKey(),
                    $event
                ),
                function ($message) use ($recipient, $institution, $event) {
                    $message->to($recipient)
                        ->subject(sprintf('Institution %s Notification: %s', $event, $institution->name ?? $institution->getKey()));
                }
            );
        } catch (\Throwable $exception) {
            Log::error('InstitutionNotificationEmailObserver: failed to send notification email.', [
                'institution_id' => $institution->getKey(),
                'event' => $event,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}