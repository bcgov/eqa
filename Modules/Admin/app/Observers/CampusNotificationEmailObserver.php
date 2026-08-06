<?php

namespace Modules\Admin\Observers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Migrated from CRM plugin: Plugins/CGI.Plugins/Notification Email/CampusNotificationEmail.cs
 * Original namespace: CGI.Plugins
 *
 * Source plugin sent a notification email when the observed entity was
 * created/updated in Dynamics. This observer replicates that behavior
 * for the corresponding Eloquent model.
 */
class CampusNotificationEmailObserver
{
    /**
     * Handle the model "created" event.
     */
    public function created(object $model): void
    {
        $this->sendNotificationEmail($model, 'created');
    }

    /**
     * Handle the model "updated" event.
     */
    public function updated(object $model): void
    {
        $this->sendNotificationEmail($model, 'updated');
    }

    /**
     * Send the campus notification email associated with the given model event.
     *
     * Mirrors the org-service driven email dispatch performed by the
     * legacy CampusNotificationEmail plugin.
     */
    protected function sendNotificationEmail(object $model, string $event): void
    {
        $recipient = $model->notification_email ?? null;

        if (empty($recipient)) {
            Log::warning('CampusNotificationEmailObserver: no recipient email found on model.', [
                'model' => get_class($model),
                'id' => $model->getKey ?? null,
                'event' => $event,
            ]);

            return;
        }

        try {
            Mail::raw(
                $this->buildMessageBody($model, $event),
                function ($message) use ($recipient, $model, $event) {
                    $message->to($recipient)
                        ->subject($this->buildSubject($model, $event));
                }
            );
        } catch (\Throwable $e) {
            Log::error('CampusNotificationEmailObserver: failed to send notification email.', [
                'model' => get_class($model),
                'id' => $model->getKey ?? null,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build the notification email subject line.
     */
    protected function buildSubject(object $model, string $event): string
    {
        return sprintf('Campus Notification: %s %s', class_basename($model), $event);
    }

    /**
     * Build the notification email body.
     */
    protected function buildMessageBody(object $model, string $event): string
    {
        return sprintf(
            "A %s record has been %s.\n\nRecord ID: %s",
            class_basename($model),
            $event,
            $model->getKey ?? 'N/A'
        );
    }
}