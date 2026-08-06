<?php

namespace Modules\Admin\Actions;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Source lineage: Plugins/CGI.Plugins/Notification Email/DbaNotificationEmail.cs (crm_plugin plugin "DbaNotificationEmail").
 *
 * Sends a DBA (Database Administrator) notification email, migrated from the legacy
 * CRM plugin that used the organization service context to build and dispatch
 * notification emails on a triggering event.
 */
class DbaNotificationEmailAction
{
    /**
     * @param string $recipient Email address of the DBA/administrator to notify.
     * @param string $subject Subject line for the notification email.
     * @param string $body Body content of the notification email.
     * @param array<string, mixed> $context Additional contextual data relevant to the notification.
     *
     * @return bool True when the notification email was dispatched successfully, false otherwise.
     */
    public function __invoke(string $recipient, string $subject, string $body, array $context = []): bool
    {
        try {
            Mail::raw($body, function ($message) use ($recipient, $subject): void {
                $message->to($recipient)->subject($subject);
            });

            Log::info('DbaNotificationEmailAction: notification email sent.', [
                'recipient' => $recipient,
                'subject' => $subject,
                'context' => $context,
            ]);

            return true;
        } catch (\Throwable $exception) {
            Log::error('DbaNotificationEmailAction: failed to send notification email.', [
                'recipient' => $recipient,
                'subject' => $subject,
                'context' => $context,
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}