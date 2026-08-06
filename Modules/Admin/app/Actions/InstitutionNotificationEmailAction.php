<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Admin\Mail\InstitutionNotificationMail;
use Modules\Admin\Models\Institution;
use Throwable;

/**
 * Sends the institution notification email.
 *
 * Migrated from CRM plugin: Plugins/CGI.Plugins/Notification Email/InstitutionNotificationEmail.cs
 * Source namespace: CGI.Plugins
 *
 * Original plugin was triggered by the organization service pipeline on an
 * institution-related entity event and dispatched a notification email to
 * the relevant recipients. This action reproduces that behavior explicitly,
 * invoked from a controller, job, or event listener rather than a CRM
 * message pipeline.
 */
class InstitutionNotificationEmailAction
{
    /**
     * Execute the action.
     *
     * @param  Institution  $institution  The institution the notification concerns.
     * @param  array<int, string>  $recipients  Email addresses to notify. Falls back to
     *                                          the institution's primary contact email
     *                                          when omitted.
     * @param  array<string, mixed>  $context  Additional data merged into the mail view
     *                                          (e.g. reason, triggering user, timestamp).
     * @return bool True when the email was queued/sent successfully, false otherwise.
     */
    public function __invoke(Institution $institution, array $recipients = [], array $context = []): bool
    {
        $recipients = $this->resolveRecipients($institution, $recipients);

        if (empty($recipients)) {
            Log::warning('InstitutionNotificationEmailAction: no recipients resolved.', [
                'institution_id' => $institution->getKey(),
            ]);

            return false;
        }

        try {
            Mail::to($recipients)->send(
                new InstitutionNotificationMail($institution, $context)
            );

            return true;
        } catch (Throwable $exception) {
            Log::error('InstitutionNotificationEmailAction: failed to send notification email.', [
                'institution_id' => $institution->getKey(),
                'recipients' => $recipients,
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Resolve the recipients for the notification, falling back to the
     * institution's registered contact email when none are explicitly given.
     *
     * @param  array<int, string>  $recipients
     * @return array<int, string>
     */
    protected function resolveRecipients(Institution $institution, array $recipients): array
    {
        if (! empty($recipients)) {
            return array_values(array_unique(array_filter($recipients)));
        }

        $fallback = $institution->contact_email ?? null;

        return $fallback ? [$fallback] : [];
    }
}