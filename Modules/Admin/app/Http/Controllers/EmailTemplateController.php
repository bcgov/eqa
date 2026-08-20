<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ministry (IDIR) management of the notification email templates migrated from
 * the legacy Dynamics notification-email plugins, plus the global master switch
 * that turns email sending on/off completely (default OFF).
 */
class EmailTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = Schema::hasTable('email_templates')
            ? DB::table('email_templates')->orderBy('category')->orderBy('name')->get()
            : collect();

        return Inertia::render('Admin/EmailTemplates', [
            'templates' => $templates,
            'sendingEnabled' => $this->sendingEnabled(),
            'testingEmail' => $this->testingEmail(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        abort_unless(Schema::hasTable('email_templates'), 404);

        $template = DB::table('email_templates')->where('id', $id)->first();
        abort_if($template === null, 404);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'recipients' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        DB::table('email_templates')->where('id', $id)->update([
            'subject' => $data['subject'],
            'body' => $data['body'],
            'recipients' => $data['recipients'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'updated_at' => now(),
        ]);

        return redirect('/admin/email-templates')->with('success', 'Email template updated.');
    }

    public function toggle(Request $request): RedirectResponse
    {
        abort_unless(Schema::hasTable('email_settings'), 404);

        $enabled = $request->boolean('sending_enabled');

        $this->persistSettings(['sending_enabled' => $enabled]);

        return redirect('/admin/email-templates')->with(
            'success',
            $enabled ? 'Email sending is now ON.' : 'Email sending is now OFF.'
        );
    }

    public function settings(Request $request): RedirectResponse
    {
        abort_unless(Schema::hasTable('email_settings'), 404);

        $data = $request->validate([
            'testing_email' => ['nullable', 'email', 'max:255'],
        ]);

        $testingEmail = trim((string) ($data['testing_email'] ?? ''));

        $this->persistSettings(['testing_email' => $testingEmail !== '' ? $testingEmail : null]);

        return redirect('/admin/email-templates')->with(
            'success',
            $testingEmail !== ''
                ? 'Testing email set — while sending is OFF, all notifications go to '.$testingEmail.'.'
                : 'Testing email cleared.'
        );
    }

    /**
     * Update (or create) the single email settings row.
     *
     * @param  array<string, mixed>  $values
     */
    private function persistSettings(array $values): void
    {
        $row = DB::table('email_settings')->orderBy('id')->first();

        if ($row === null) {
            DB::table('email_settings')->insert(array_merge($values, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            return;
        }

        DB::table('email_settings')->where('id', $row->id)->update(array_merge($values, [
            'updated_at' => now(),
        ]));
    }

    private function sendingEnabled(): bool
    {
        if (! Schema::hasTable('email_settings')) {
            return false;
        }

        $row = DB::table('email_settings')->orderBy('id')->first();

        return (bool) ($row->sending_enabled ?? false);
    }

    private function testingEmail(): string
    {
        if (! Schema::hasTable('email_settings')) {
            return '';
        }

        $row = DB::table('email_settings')->orderBy('id')->first();

        return trim((string) ($row->testing_email ?? ''));
    }
}
