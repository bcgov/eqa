<?php

namespace Modules\Admin\Observers;

use Modules\Admin\Models\DbaNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Migrated from CRM plugin: Plugins/CGI.Plugins/Notification Email/DbaNotificationEmail.cs
 * Original namespace: CGI.Plugins
 * Sends a notification email when the observed model changes.
 */
class DbaNotificationEmailObserver
{
    /**
     * Handle the DbaNotification "created" event.
     */
    public function created(DbaNotification $dbaNotification): void
    {
        $this->sendNotificationEmail($dbaNotification, 'created');
    }

    /**
     * Handle the DbaNotification "updated" event.
     */
    public function updated(DbaNotification $dbaNotification): void
    {
        $this->sendNotificationEmail($dbaNotification, 'updated');
    }

    /**
     * Handle the DbaNotification "deleted" event.
     */
    public function deleted(DbaNotification $dbaNotification): void
    {
        $this->sendNotificationEmail($dbaNotification, 'deleted');
    }

    /**
     * Handle the DbaNotification "restored" event.
     */
    public function restored(DbaNotification $dbaNotification): void
    {
        $this->sendNotificationEmail($dbaNotification, 'restored');
    }

    /**
     * Handle the DbaNotification "force deleted" event.
     */
    public function forceDeleted(DbaNotification $dbaNotification): void
    {
        $this->sendNotificationEmail($dbaNotification, 'forceDeleted');
    }

    /**
     * Send the notification email for the given model event.
     * Mirrors the org-service driven email dispatch from the original CRM plugin.
     */
    protected function sendNotificationEmail(DbaNotification $dbaNotification, string $event): void
    {
        $recipient = $dbaNotification->notification_email ?? null;

        if (empty($recipient)) {
            Log::warning('DbaNotificationEmailObserver: no recipient email found for notification', [
                'id' => $dbaNotification->getKey(),
                'event' => $event,
            ]);

            return;
        }

        try {
            Mail::raw(
                sprintf(
                    'Notification: record %s was %s.',
                    $dbaNotification->getKey(),
                    $event
                ),
                function ($message) use ($recipient) {
                    $message->to($recipient)
                        ->subject('DBA Notification Email');
                }
            );
        } catch (\Throwable $exception) {
            Log::error('DbaNotificationEmailObserver: failed to send notification email', [
                'id' => $dbaNotification->getKey(),
                'event' => $event,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}