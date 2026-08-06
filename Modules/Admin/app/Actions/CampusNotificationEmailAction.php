<?php

namespace Modules\Admin\Actions;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Migrated from CRM plugin CampusNotificationEmail.
 * Source: Plugins/CGI.Plugins/Notification Email/CampusNotificationEmail.cs
 *
 * Sends a campus notification email as part of a legacy Dynamics CRM
 * plugin workflow. The original plugin used the CRM organization
 * service context to resolve recipient and message data before
 * dispatching the notification email.
 */
class CampusNotificationEmailAction
{
    /**
     * Execute the campus notification email action.
     *
     * @param array $payload Data required to build and send the notification,
     *                        e.g. ['to' => string, 'subject' => string, 'body' => string,
     *                        'context' => array]
     * @return bool True on successful dispatch, false otherwise.
     */
    public function __invoke(array $payload): bool
    {
        $to = $payload['to'] ?? null;
        $subject = $payload['subject'] ?? 'Campus Notification';
        $body = $payload['body'] ?? '';
        $context = $payload['context'] ?? [];

        if (empty($to)) {
            Log::warning('CampusNotificationEmailAction: missing recipient email address.', [
                'payload' => $payload,
            ]);

            return false;
        }

        try {
            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            Log::info('CampusNotificationEmailAction: notification email sent.', [
                'to' => $to,
                'subject' => $subject,
                'context' => $context,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('CampusNotificationEmailAction: failed to send notification email.', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}