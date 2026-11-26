<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Admin-maintained application setting (Maintenance > Utils), after bcgov/fsg's Util model.
 * Active rows override the matching config('eqa.*') default, so code keeps reading config().
 */
class Util extends Model
{
    private const CACHE_KEY = 'utils.active_values';

    /** field_key => config path it overrides. */
    public const CONFIG_MAP = [
        'ministry_name' => 'eqa.ministry.name',
        'branch_name' => 'eqa.ministry.branch',
        'program_name' => 'eqa.ministry.program',
        'mailing_address' => 'eqa.ministry.address',
        'contact_email' => 'eqa.ministry.email',
        'privacy_email' => 'eqa.ministry.privacy_email',
        'fee_application_new' => 'eqa.fees.application_new',
        'fee_application_renewal' => 'eqa.fees.application_renewal',
        'fee_appeal' => 'eqa.fees.appeal',
        'fee_multi_location' => 'eqa.fees.multi_location',
        'fee_per_international_student' => 'eqa.fees.per_international_student',
        'deadline_admin_fee_days' => 'eqa.deadlines.admin_fee_days',
        'deadline_appeal_days' => 'eqa.deadlines.appeal_days',
        'deadline_changes_days' => 'eqa.deadlines.changes_days',
        'deadline_application_days' => 'eqa.deadlines.application_days',
        'deadline_application_reminder_days' => 'eqa.deadlines.application_reminder_days',
        'invoice_title' => 'eqa.invoice.title',
        'invoice_payment_online' => 'eqa.invoice.payment_online',
        'invoice_payment_offline' => 'eqa.invoice.payment_offline',
        'invoice_due_text' => 'eqa.invoice.due_text',
        'invoice_paid_message' => 'eqa.invoice.paid_message',
        'invoice_notes' => 'eqa.invoice.notes',
        'invoice_footer' => 'eqa.invoice.footer',
        'invoice_receipt_title' => 'eqa.invoice.receipt_title',
        'invoice_receipt_message' => 'eqa.invoice.receipt_message',
        'help_total_enrolment' => 'eqa.help.total_enrolment',
        'help_intl_students_permit' => 'eqa.help.intl_students_permit',
        'help_in_person_students' => 'eqa.help.in_person_students',
        'help_online_students' => 'eqa.help.online_students',
        'help_per_student_fee_enrolment' => 'eqa.help.per_student_fee_enrolment',
    ];

    /** @var array<string, mixed>|null config defaults captured before the first override */
    private static ?array $defaults = null;

    protected $fillable = ['field_name', 'field_description', 'active_flag'];

    protected function casts(): array
    {
        return ['active_flag' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(function (): void {
            Cache::forget(self::CACHE_KEY);
            self::applyToConfig();
        });
    }

    /**
     * Point config at the active Utils values (or back at the defaults for inactive rows).
     * Silently keeps the defaults when the table is not available (e.g. before migrating).
     */
    public static function applyToConfig(): void
    {
        self::$defaults ??= collect(self::CONFIG_MAP)->mapWithKeys(fn (string $path) => [$path => config($path)])->all();

        try {
            $values = Cache::rememberForever(self::CACHE_KEY, fn (): array => self::query()
                ->where('active_flag', true)
                ->whereIn('field_key', array_keys(self::CONFIG_MAP))
                ->pluck('field_name', 'field_key')
                ->all());
        } catch (Throwable) {
            return;
        }

        foreach (self::CONFIG_MAP as $key => $path) {
            $value = $values[$key] ?? null;
            config([$path => $value === null || $value === '' ? self::$defaults[$path] : self::cast($path, $value)]);
        }
    }

    private static function cast(string $path, string $value): mixed
    {
        return match (true) {
            str_starts_with($path, 'eqa.fees.') => (float) $value,
            str_starts_with($path, 'eqa.deadlines.') => (int) $value,
            default => $value,
        };
    }
}
