<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Centralizes the EQA designation business rules migrated from the Dynamics CRM
 * "Application Approval Process" BPF and the Institution "Designation Information"
 * form. An institution's Designated status (EQA Status), EQA Standing and PTIRU
 * Standing are tightly coupled to its application(s):
 *
 *  - Approving an application (Designation Decision = Approved) designates the
 *    institution: EQA Status = Designated and the designation start/expiry dates
 *    are stamped.                                        [application -> institution]
 *  - Not approving a re-application de-designates a Designated institution
 *    (De-Designated, dated today); a never-designated applicant becomes Not
 *    Approved. A designation whose expiry passes without an approved renewal is
 *    de-designated by the daily deadline job.          [application -> institution]
 *  - An application's "EQA in Good Standing" / "PTIRU in Good Standing" are a locked
 *    copy of the institution's EQA / PTIRU Standing (read-only on the CRM app form),
 *    so changing an institution's standing cascades onto its OPEN applications.
 *                                                        [institution -> application]
 */
class DesignationService
{
    /** eqa_designationstatus option set (institution EQA Status). */
    public const EQA_STATUSES = ['Pending', 'Designated', 'Not Approved', 'De-Designated'];

    /** eqa_eqastanding / eqa_ptibstanding option set. */
    public const STANDINGS = ['In Good Standing', 'Not in Good Standing', 'Under Review', 'Denied'];

    /**
     * QA Met Through value that ties the institution to the Private Training
     * Institutions Regulatory Unit, making PTIRU Standing relevant to its EQA
     * designation. (Formerly the "Private Training Institutions Branch (PTIB)".)
     */
    public const PTIRU_QA_MET_THROUGH = 'Private Training Institutions Regulatory Unit (PTIRU) Designation';

    /**
     * Legacy label for the same QA pathway as it was stored in Dynamics before
     * the PTIB -> PTIRU rename. Migrated data may still carry this value, so the
     * matching logic accepts it as equivalent to PTIRU_QA_MET_THROUGH.
     */
    public const LEGACY_PTIB_QA_MET_THROUGH = 'Private Training Institutions Branch (PTIB) Designation';

    /**
     * Workflow stages where an application is still open, so its locked
     * good-standing copy should track the institution's current standing.
     */
    private const OPEN_STAGES = [
        'draft', 'fee_required', 'fees_payment', 'pending_review', 'under_review', 'changes_requested',
        'application_approval', 'suitability_review', 'not_approved', 'non_approval_reasons', 'pending_expiry',
    ];

    public static function standingIsGood(?string $standing): bool
    {
        return $standing === 'In Good Standing';
    }

    /**
     * PTIRU Standing is only relevant — and required — when the institution's
     * quality assurance is met through the Private Training designation. This is
     * backward compatible: it matches the pathway whether QA Met Through carries
     * the legacy "Branch (PTIB)" label or the renamed "Regulatory Unit (PTIRU)"
     * label (any value tagged "(PTIB)" or "(PTIRU)"). For every other QA pathway
     * PTIRU Standing has no bearing on the designation.
     */
    public static function ptiruRequired(?string $qaMetThrough): bool
    {
        if ($qaMetThrough === null) {
            return false;
        }

        return str_contains($qaMetThrough, '(PTIB)') || str_contains($qaMetThrough, '(PTIRU)');
    }

    /**
     * Whether the PTIRU Standing permits EQA designation. When QA is met through
     * PTIRU Designation the institution can only be Designated while its PTIRU
     * Standing is In Good Standing; otherwise PTIRU Standing is ignored.
     */
    public static function ptiruPermitsDesignation(?string $qaMetThrough, ?string $ptiruStanding): bool
    {
        if (! self::ptiruRequired($qaMetThrough)) {
            return true;
        }

        return self::standingIsGood($ptiruStanding);
    }

    /**
     * Ministry directly sets an institution's designation status / standing.
     * Applies the coupled defaults and cascades standing onto open applications,
     * all in one transaction.
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
                'eqa_standing' => $data['eqa_standing'] ?? $institution->eqa_standing,
                'ptib_standing' => $data['ptib_standing'] ?? $institution->ptib_standing,
                'designation_start' => $data['designation_start'] ?? $institution->designation_start,
                'designation_expiry' => $data['designation_expiry'] ?? $institution->designation_expiry,
            ];

            // PTIRU gate: when QA is met through PTIRU Designation, an institution
            // can only be Designated while its PTIRU Standing is In Good Standing.
            if ($updates['eqa_status'] === 'Designated'
                && ! self::ptiruPermitsDesignation($institution->qa_met_through, $updates['ptib_standing'])) {
                $updates['eqa_status'] = 'De-Designated';
            }

            // Becoming Designated stamps sensible defaults (mirrors the approval BPF).
            if ($updates['eqa_status'] === 'Designated') {
                $updates['designation_start'] = $updates['designation_start'] ?: Carbon::now()->toDateString();
                if (empty($updates['designation_expiry'])) {
                    $updates['designation_expiry'] = Carbon::parse($updates['designation_start'])->addYear()->toDateString();
                }
                if (empty($updates['eqa_standing'])) {
                    $updates['eqa_standing'] = 'In Good Standing';
                }
                $updates['de_designated_date'] = null;
            } elseif ($institution->eqa_status === 'Designated') {
                $updates['de_designated_date'] = self::today();
            }

            DB::table('institutions')->where('crm_id', $instCrmId)->update($updates);

            $this->cascadeStandingToApplications($instCrmId, $updates['eqa_standing'], $updates['ptib_standing']);
        });
    }

    /**
     * Application approved -> institution becomes Designated. Keeps an existing
     * designation start date (renewals) and adopts the application's expiry.
     * Returns false when the PTIRU gate prevents the designation.
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

        // PTIRU gate: an approved application cannot designate an institution whose
        // QA is met through PTIRU Designation while its PTIRU Standing is not good.
        if (! self::ptiruPermitsDesignation($institution->qa_met_through, $institution->ptib_standing)) {
            return false;
        }

        $updates = [
            'eqa_status' => 'Designated',
            'designation_start' => $institution->designation_start ?: $approvedDate,
            'designation_expiry' => $designationExpiry ?: $institution->designation_expiry,
            'de_designated_date' => null,
        ];
        if (empty($institution->eqa_standing)) {
            $updates['eqa_standing'] = 'In Good Standing';
        }

        DB::table('institutions')->where('crm_id', $application->institution_crm_id)->update($updates);

        return true;
    }

    /**
     * The ministry approved an application of an institution that is not currently Designated (a
     * first-time applicant, or one re-applying after a decline or de-designation): it is Designated
     * now, without waiting for the administrative fee. Its previous EQA Status is kept on the
     * application so an unpaid fee can undo it. Returns whether the institution is now Designated.
     */
    public function designateOnApproval(object $application, string $approvedDate, ?string $designationExpiry): bool
    {
        $institution = empty($application->institution_crm_id)
            ? null
            : DB::table('institutions')->where('crm_id', $application->institution_crm_id)->first();
        if ($institution === null) {
            return false;
        }
        if ($institution->eqa_status === 'Designated') {
            return true;
        }

        if (! $this->onApplicationApproved($application, $approvedDate, $designationExpiry)) {
            return false;
        }
        DB::table('applications')->where('crm_id', $application->crm_id)->update(['prior_eqa_status' => $institution->eqa_status ?: 'Pending']);

        return true;
    }

    /**
     * The administrative fee of an application that designated the institution on approval was never
     * paid: the institution goes back to not designated (Not Approved for a first-time applicant).
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
     * QA Met Through changed on the institution profile. Because PTIRU Standing
     * only gates designation under the PTIRU pathway, switching QA to (or the PTIRU
     * Standing being inconsistent with) PTIRU Designation can invalidate an
     * existing Designated status — reconcile it to De-Designated.
     */
    public function reconcileAfterQaChange(string $instCrmId): void
    {
        $institution = DB::table('institutions')->where('crm_id', $instCrmId)->first();
        if ($institution === null) {
            return;
        }

        if ($institution->eqa_status === 'Designated'
            && ! self::ptiruPermitsDesignation($institution->qa_met_through, $institution->ptib_standing)) {
            DB::table('institutions')->where('crm_id', $instCrmId)->update(['eqa_status' => 'De-Designated', 'de_designated_date' => self::today()]);
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
     * Daily sweep: a Designated institution whose designation expiry has passed is
     * De-Designated (dated today) unless an approved renewal carries it forward, i.e.
     * an application approved with an expiry still ahead (including one awaiting its
     * administrative fee). Returns the number of institutions de-designated.
     */
    public function deDesignateLapsed(): int
    {
        $today = self::today();

        return DB::table('institutions')
            ->where('eqa_status', 'Designated')
            ->whereNotNull('designation_expiry')
            ->where('designation_expiry', '<', $today)
            ->whereNotExists(function ($q) use ($today): void {
                $q->select(DB::raw(1))
                    ->from('applications as a')
                    ->whereColumn('a.institution_crm_id', 'institutions.crm_id')
                    ->where('a.status', 'like', 'Approved%')
                    ->where('a.designation_expiry', '>=', $today);
            })
            ->update(['eqa_status' => 'De-Designated', 'de_designated_date' => $today]);
    }

    /**
     * The application's locked good-standing flags derive from the institution's
     * current standing (the CRM copies these at review time).
     *
     * @return array{eqa_good_standing: bool, ptib_good_standing: bool}
     */
    public function standingFromInstitution(?string $instCrmId): array
    {
        $institution = $instCrmId
            ? DB::table('institutions')->where('crm_id', $instCrmId)->first()
            : null;

        return [
            'eqa_good_standing' => self::standingIsGood($institution->eqa_standing ?? null),
            'ptib_good_standing' => self::standingIsGood($institution->ptib_standing ?? null),
        ];
    }

    private static function today(): string
    {
        return Carbon::now('America/Vancouver')->toDateString();
    }

    private function cascadeStandingToApplications(string $instCrmId, ?string $eqaStanding, ?string $ptibStanding): void
    {
        DB::table('applications')
            ->where('institution_crm_id', $instCrmId)
            ->whereIn('workflow_stage', self::OPEN_STAGES)
            ->update([
                'eqa_good_standing' => self::standingIsGood($eqaStanding),
                'ptib_good_standing' => self::standingIsGood($ptibStanding),
            ]);
    }
}
