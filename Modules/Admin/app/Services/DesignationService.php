<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Centralizes the EQA designation business rules migrated from the Dynamics CRM
 * "Application Approval Process" BPF and the Institution "Designation Information"
 * form. An institution's Designated status (EQA Status) is tightly coupled to its
 * application(s):
 *
 *  - Approving an application (Designation Decision = Approved, recorded once its
 *    administrative fee is paid and confirmed) designates the institution: EQA Status =
 *    Designated and the designation start/expiry dates are stamped. [application -> institution]
 *  - Declining a re-application de-designates a Designated institution
 *    (De-Designated, dated today); a never-designated applicant becomes Not
 *    Approved.                                          [application -> institution]
 *  - The designation expiry date does not de-designate on its own: the institution stays
 *    Designated until an application is declined or the ministry changes it.
 *
 * EQA / PTIRU Standing (and the application's "in Good Standing" copies) are retired:
 * not shown, never written, and they no longer gate designation.
 */
class DesignationService
{
    /** eqa_designationstatus option set (institution EQA Status). */
    public const EQA_STATUSES = ['Pending', 'Designated', 'Not Approved', 'De-Designated'];

    /**
     * QA Met Through value that ties the institution to the Private Training
     * Institutions Regulatory Unit (PTIRU-only website compliance items apply).
     * (Formerly the "Private Training Institutions Branch (PTIB)".)
     */
    public const PTIRU_QA_MET_THROUGH = 'Private Training Institutions Regulatory Unit (PTIRU) Designation';

    /**
     * Legacy label for the same QA pathway as it was stored in Dynamics before
     * the PTIB -> PTIRU rename. Migrated data may still carry this value, so the
     * matching logic accepts it as equivalent to PTIRU_QA_MET_THROUGH.
     */
    public const LEGACY_PTIB_QA_MET_THROUGH = 'Private Training Institutions Branch (PTIB) Designation';

    /**
     * Whether the institution's quality assurance is met through the Private Training
     * designation. Backward compatible: matches QA Met Through carrying the legacy
     * "Branch (PTIB)" label or the renamed "Regulatory Unit (PTIRU)" label.
     */
    public static function ptiruRequired(?string $qaMetThrough): bool
    {
        if ($qaMetThrough === null) {
            return false;
        }

        return str_contains($qaMetThrough, '(PTIB)') || str_contains($qaMetThrough, '(PTIRU)');
    }

    /**
     * Ministry directly sets an institution's designation status and dates.
     *
     * @param array<string, mixed> $data validated designation fields
     */
    public function applyInstitutionDesignation(string $instCrmId, array $data): void
    {
        DB::transaction(function () use ($instCrmId, $data): void {
            $institution = DB::table('institutions')->where('crm_id', $instCrmId)->first();
            if ($institution === null) {
                return;
            }

            $updates = [
                'eqa_status' => $data['eqa_status'] ?? $institution->eqa_status,
                'designation_start' => $data['designation_start'] ?? $institution->designation_start,
                'designation_expiry' => $data['designation_expiry'] ?? $institution->designation_expiry,
            ];

            // Becoming Designated stamps sensible defaults (mirrors the approval BPF).
            if ($updates['eqa_status'] === 'Designated') {
                $updates['designation_start'] = $updates['designation_start'] ?: Carbon::now()->toDateString();
                if (empty($updates['designation_expiry'])) {
                    $updates['designation_expiry'] = Carbon::parse($updates['designation_start'])->addYear()->toDateString();
                }
                $updates['de_designated_date'] = null;
            } elseif ($institution->eqa_status === 'Designated') {
                $updates['de_designated_date'] = self::today();
            }

            DB::table('institutions')->where('crm_id', $instCrmId)->update($updates);
        });
    }

    /**
     * Application approved -> institution becomes Designated. Keeps an existing
     * designation start date (renewals) and adopts the application's expiry.
     * Returns false when there is no institution to designate.
     */
    public function onApplicationApproved(object $application, string $approvedDate, ?string $designationExpiry): bool
    {
        if (empty($application->institution_crm_id)) {
            return false;
        }
        $institution = DB::table('institutions')->where('crm_id', $application->institution_crm_id)->first();
        if ($institution === null) {
            return false;
        }

        DB::table('institutions')->where('crm_id', $application->institution_crm_id)->update([
            'eqa_status' => 'Designated',
            'designation_start' => $institution->designation_start ?: $approvedDate,
            'designation_expiry' => $designationExpiry ?: $institution->designation_expiry,
            'de_designated_date' => null,
        ]);

        return true;
    }

    /**
     * An application designated its institution on approval under the earlier flow (before the
     * administrative fee moved ahead of the approval) but the fee was never paid: the institution
     * goes back to not designated (Not Approved for a first-time applicant).
     */
    public function undoDesignationOnApproval(object $application): void
    {
        $prior = $application->prior_eqa_status ?? null;
        if ($prior === null || empty($application->institution_crm_id)) {
            return;
        }
        $institution = DB::table('institutions')->where('crm_id', $application->institution_crm_id)->first();
        if ($institution === null || $institution->eqa_status !== 'Designated') {
            return;
        }

        $updates = in_array($prior, ['', 'Pending', 'Not Approved'], true)
            ? ['eqa_status' => 'Not Approved', 'designation_start' => null, 'designation_expiry' => null]
            : ['eqa_status' => $prior, 'de_designated_date' => self::today()];

        DB::table('institutions')->where('crm_id', $application->institution_crm_id)->update($updates);
    }

    /** Institution enrolment follows the most recent approved application (the ministry can still edit it). */
    public function syncEnrolmentFromApplication(object $application): void
    {
        if (empty($application->institution_crm_id)) {
            return;
        }
        $updates = collect(['total_enrolment', 'intl_students_permit', 'in_person_students', 'online_students'])
            ->mapWithKeys(fn (string $field): array => [$field => $application->{$field} ?? null])
            ->filter(fn ($value): bool => $value !== null)
            ->all();

        if ($updates !== []) {
            DB::table('institutions')->where('crm_id', $application->institution_crm_id)->update($updates);
        }
    }

    /**
     * Application not approved. A declined re-application de-designates a Designated
     * institution (de-designated today); a never-designated institution (EQA Status
     * Pending / unset) becomes Not Approved, since de-designation doesn't apply to a
     * first-time applicant. An institution already De-Designated keeps its original date.
     */
    public function onApplicationNotApproved(object $application): void
    {
        if (empty($application->institution_crm_id)) {
            return;
        }
        $institution = DB::table('institutions')->where('crm_id', $application->institution_crm_id)->first();
        if ($institution === null) {
            return;
        }
        if ($institution->eqa_status === 'Designated') {
            DB::table('institutions')
                ->where('crm_id', $application->institution_crm_id)
                ->update(['eqa_status' => 'De-Designated', 'de_designated_date' => self::today()]);
        } elseif (in_array($institution->eqa_status, [null, '', 'Pending'], true)) {
            DB::table('institutions')
                ->where('crm_id', $application->institution_crm_id)
                ->update(['eqa_status' => 'Not Approved']);
        }
    }

    /**
     * A designation does not lapse on its expiry date: the institution stays Designated until an
     * application is declined or the ministry changes its EQA Status (a renewal applied for on
     * the last day may not be processed in time).
     */
    private static function today(): string
    {
        return Carbon::now('America/Vancouver')->toDateString();
    }
}
