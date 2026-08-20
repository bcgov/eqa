<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Central gateway for the notification emails migrated from the legacy Dynamics
 * notification-email plugins. Every send is gated by the global master switch
 * (email_settings.sending_enabled), which defaults to OFF — so nothing goes out
 * until the ministry explicitly turns sending on from the admin Email Templates
 * page.
 */
class EmailNotificationService
{
    /**
     * The single global email settings row (or null when unavailable).
     */
    private function settings(): ?object
    {
        if (! Schema::hasTable('email_settings')) {
            return null;
        }

        return DB::table('email_settings')->orderBy('id')->first();
    }

    /**
     * Is the global master switch on? Defaults to OFF when unset/missing.
     */
    public function sendingEnabled(): bool
    {
        return (bool) ($this->settings()->sending_enabled ?? false);
    }

    /**
     * The testing redirect address, if one is configured. When set and the
     * master switch is OFF, every notification is diverted here instead of the
     * normal recipient.
     */
    public function testingEmail(): string
    {
        return trim((string) ($this->settings()->testing_email ?? ''));
    }

    /**
     * Render + send a template by key. No-ops (returns false) when the template
     * is missing/inactive, no recipient resolves, or sending is fully off. When
     * the master switch is OFF but a testing_email is configured, the message is
     * still sent — but redirected to that testing address (a safe preview mode).
     * Never throws — a failed notification must not break the workflow action.
     * Every actual send/attempt is recorded in the sent_emails audit log;
     * $meta may carry an 'institution_crm_id' to link the log to an institution.
     *
     * @param  array<string, scalar|null>  $vars
     * @param  array<string, scalar|null>  $meta
     */
    public function send(string $key, array $vars = [], ?string $to = null, array $meta = []): bool
    {
        $settings = $this->settings();
        $sendingEnabled = (bool) ($settings->sending_enabled ?? false);
        $testingEmail = trim((string) ($settings->testing_email ?? ''));

        // Fully off: master switch OFF and no testing address to divert to.
        if (! $sendingEnabled && $testingEmail === '') {
            return false;
        }

        if (! Schema::hasTable('email_templates')) {
            return false;
        }

        $template = DB::table('email_templates')->where('key', $key)->first();

        if ($template === null || ! (bool) $template->is_active) {
            return false;
        }

        // Resolve the intended recipient first (template/process address), then
        // apply the testing redirect so the audit log can record both.
        $intended = $to ?: (string) ($template->recipients ?? '');
        $intended = trim(explode(',', $intended)[0] ?? '');
        $redirected = false;

        if (! $sendingEnabled && $testingEmail !== '') {
            // Testing mode — divert everything to the configured test address
            // regardless of the recipient set on the template/process.
            $recipient = $testingEmail;
            $redirected = $intended !== '' && $intended !== $testingEmail;
        } else {
            $recipient = $intended;
        }

        if ($recipient === '') {
            return false;
        }

        $subject = $this->render((string) $template->subject, $vars);
        $body = $this->render((string) $template->body, $vars);

        try {
            Mail::html($body, function ($message) use ($recipient, $subject): void {
                $message->to($recipient)->subject($subject);
            });

            $this->log($key, $template, $subject, $body, $recipient, $redirected ? $intended : null, 'sent', null, $meta);

            return true;
        } catch (Throwable $e) {
            Log::warning('EmailNotificationService: failed to send "'.$key.'": '.$e->getMessage());

            $this->log($key, $template, $subject, $body, $recipient, $redirected ? $intended : null, 'failed', $e->getMessage(), $meta);

            return false;
        }
    }

    /**
     * Persist an audit record of a dispatched (or attempted) email. Never throws
     * — logging must not break the actual send/workflow.
     *
     * @param  array<string, scalar|null>  $meta
     */
    private function log(string $key, object $template, string $subject, string $body, string $recipient, ?string $intended, string $status, ?string $error, array $meta): void
    {
        if (! Schema::hasTable('sent_emails')) {
            return;
        }

        try {
            DB::table('sent_emails')->insert([
                'institution_crm_id' => $meta['institution_crm_id'] ?? null,
                'template_key' => $key,
                'template_name' => (string) ($template->name ?? ''),
                'subject' => $subject,
                'body' => $body,
                'recipient' => $recipient,
                'intended_recipient' => $intended,
                'status' => $status,
                'error' => $error,
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('EmailNotificationService: failed to log "'.$key.'": '.$e->getMessage());
        }
    }

    /**
     * Replace {{token}} placeholders with the provided values.
     *
     * @param  array<string, scalar|null>  $vars
     */
    public function render(string $template, array $vars): string
    {
        foreach ($vars as $name => $value) {
            $template = str_replace('{{'.$name.'}}', (string) $value, $template);
        }

        return $template;
    }
}
