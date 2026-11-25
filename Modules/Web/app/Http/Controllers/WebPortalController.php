<?php

declare(strict_types=1);

namespace Modules\Web\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\ExpressPayService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Models\Application;
use Modules\Admin\Services\ApplicationFeeService;
use Modules\Admin\Services\ApplicationProfileService;
use Modules\Admin\Services\ProcessNotifier;
use Modules\Admin\Services\WebsiteComplianceService;

/**
 * The institution (BCeID) web portal: an institution views its own profile
 * and application history, opens an application, and submits a new one. The
 * logged-in institution is bound at login (session portal_institution); real
 * BCeID SSO would map the authenticated user to their institution.
 */
class WebPortalController extends Controller
{
    /** Number of steps of the application form (Web/NewApplication.vue). */
    private const APPLICATION_STEPS = 6;

    // sabc_designation is retired: no longer asked, and never written, so values on older applications are kept.
    private const APPLICATION_FLAGS = [
        'program_associate_degree', 'program_university_transfer', 'program_bachelors_degree', 'program_graduate_degree',
        'program_career_training', 'program_language_training', 'program_theological_education', 'program_trades_apprenticeship',
        'media_pamphlet', 'media_website', 'media_brochure', 'media_poster', 'media_banner', 'media_billboard',
        'annual_report_filed',
    ];

    // affirm_branding_guide and affirm_understands_comply are retired (folded into the designation-mark
    // affirmation): no longer asked, never written, so older applications keep their answers.
    private const APPLICATION_AFFIRMATIONS = [
        'affirm_policy_manual', 'affirm_website_compliance', 'affirm_written_permission', 'affirm_authorized',
    ];

    private function institution(Request $request): ?object
    {
        $id = $request->session()->get('portal_institution');

        return $id && DB::getSchemaBuilder()->hasTable('institutions')
            ? DB::table('institutions')->where('crm_id', $id)->first()
            : null;
    }

    private function applicationsFor(?object $institution)
    {
        if ($institution === null || ! DB::getSchemaBuilder()->hasTable('applications')) {
            return collect();
        }

        return DB::table('applications')
            ->where('institution_crm_id', $institution->crm_id)
            ->orderByDesc('application_date')
            ->get();
    }

    private function hasActiveApplication(object $institution): bool
    {
        return DB::getSchemaBuilder()->hasTable('applications')
            && DB::table('applications')
                ->where('institution_crm_id', $institution->crm_id)
                ->whereIn('workflow_stage', Application::ACTIVE_STAGES)
                ->exists();
    }

    private function campusesFor(?object $institution)
    {
        if ($institution === null || ! DB::getSchemaBuilder()->hasTable('campuses')) {
            return collect();
        }

        return DB::table('campuses')
            ->where('institution_crm_id', $institution->crm_id)
            ->orderByDesc('primary_location')
            ->orderBy('name')
            ->get();
    }

    private function usersFor(?object $institution)
    {
        if ($institution === null || ! DB::getSchemaBuilder()->hasTable('institution_users')) {
            return collect();
        }

        $contacts = DB::table('institution_users')
            ->where('institution_crm_id', $institution->crm_id)
            ->orderBy('full_name')
            ->get();

        $guids = $contacts->pluck('bceid_user_guid')->filter()->map(fn ($g) => Str::lower($g))->all();
        $emails = $contacts->pluck('email')->filter()->map(fn ($e) => Str::lower($e))->all();

        $accounts = ($guids === [] && $emails === [])
            ? collect()
            : User::with('roles')
                ->where(function ($q) use ($guids, $emails): void {
                    if ($guids !== []) {
                        $q->orWhereIn(DB::raw('lower(bceid_user_guid)'), $guids);
                    }
                    if ($emails !== []) {
                        $q->orWhereIn(DB::raw('lower(email)'), $emails);
                    }
                })
                ->get();

        return $contacts->map(function (object $contact) use ($accounts) {
            $account = $accounts->first(fn (User $u) => $this->accountMatchesContact($u, $contact));
            $roles = $account ? $account->roles->pluck('name') : collect();

            $contact->user_id = $account?->id;
            $contact->access_type = $account === null ? null
                : ($roles->contains(Role::INSTITUTION_ADMIN) ? 'Admin'
                    : ($roles->contains(Role::INSTITUTION_GUEST) ? 'Guest' : 'User'));
            $contact->account_disabled = $account ? (bool) $account->disabled : null;

            return $contact;
        });
    }

    /** Does this BCeID account correspond to the given institution contact? */
    private function accountMatchesContact(User $user, object $contact): bool
    {
        if (! empty($contact->bceid_user_guid) && ! empty($user->bceid_user_guid)
            && Str::lower($user->bceid_user_guid) === Str::lower($contact->bceid_user_guid)) {
            return true;
        }

        if (! empty($contact->bceid_username) && ! empty($user->bceid_username)
            && Str::upper($user->bceid_username) === Str::upper($contact->bceid_username)) {
            return true;
        }

        $contactHasBceid = ! empty($contact->bceid_username) || ! empty($contact->bceid_user_guid);
        $userHasBceid = ! empty($user->bceid_username) || ! empty($user->bceid_user_guid);

        return ! $contactHasBceid && ! $userHasBceid
            && ! empty($contact->email) && ! empty($user->email)
            && Str::lower($user->email) === Str::lower($contact->email);
    }

    /** Guard: the target account must be one of this institution's contacts. */
    private function accountBelongsToInstitution(User $user, object $institution): bool
    {
        if (! DB::getSchemaBuilder()->hasTable('institution_users')) {
            return false;
        }

        return DB::table('institution_users')
            ->where('institution_crm_id', $institution->crm_id)
            ->get()
            ->contains(fn (object $contact) => $this->accountMatchesContact($user, $contact));
    }

    public function home(Request $request): Response
    {
        $institution = $this->institution($request);

        return Inertia::render('Web/Home', [
            'institution' => $institution,
            'applications' => $this->applicationsFor($institution)->take(6)->values(),
        ]);
    }

    public function applications(Request $request): Response
    {
        $institution = $this->institution($request);

        return Inertia::render('Web/Applications', [
            'institution' => $institution,
            'applications' => $this->applicationsFor($institution),
            'hasActiveApplication' => $institution !== null && $this->hasActiveApplication($institution),
        ]);
    }

    public function show(Request $request, string $crmId): Response
    {
        $institution = $this->institution($request);
        $application = $this->ownApplication($request, $crmId);
        $expressPay = app(ExpressPayService::class);
        $fees = app(ApplicationFeeService::class);

        $invoices = DB::table('invoices')
            ->where('application_crm_id', $application->crm_id)
            ->orderBy('invoice_number')
            ->get()
            ->map(function (object $invoice) use ($expressPay, $fees, $application): object {
                $invoice->amount_due = $expressPay->amountDue($invoice);
                $invoice->payable = $expressPay->isPayable($invoice) && ! $fees->awaitingSubmission($application);
                $invoice->payment_under_review = $expressPay->hasPaymentUnderReview((string) $invoice->crm_id);

                return $invoice;
            });

        return Inertia::render('Web/ViewApplication', [
            'institution' => $institution,
            'application' => $application,
            'invoices' => $invoices,
            'canPayOnline' => $expressPay->canUserPay($request->user(), $request->session()->has('impersonator_id')),
            'canEdit' => $fees->editableByInstitution($application),
            'canAppeal' => $fees->canAppeal($application),
            'awaitingSubmission' => $fees->awaitingSubmission($application),
            'campusLocations' => app(ApplicationProfileService::class)->campusLocations($application->crm_id),
            'previousNames' => app(ApplicationProfileService::class)->previousNames($application->crm_id),
            'websiteCompliance' => app(WebsiteComplianceService::class)->copy($application->crm_id),
        ]);
    }

    public function institutionProfile(Request $request): Response
    {
        $institution = $this->institution($request);

        return Inertia::render('Web/Institution', [
            'institution' => $institution,
            'campusCount' => $this->campusesFor($institution)->count(),
            'userCount' => $this->usersFor($institution)->count(),
        ]);
    }

    public function editInstitution(Request $request): Response
    {
        $institution = $this->institution($request);
        abort_if($institution === null, 403);

        return Inertia::render('Web/EditInstitution', [
            'institution' => $institution,
            'qaOptions' => [
                'Private Training Institutions Branch (PTIB) Designation',
                'Public Institution Legislation',
                'Ministry Review Process',
                'Ministers consent under the Degree Authorization Act (DAA)',
                'Languages Canada Accreditation',
            ],
        ]);
    }

    public function updateInstitution(Request $request): RedirectResponse
    {
        $institution = $this->institution($request);
        abort_if($institution === null, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'bc_incorporation_number' => ['nullable', 'string', 'max:100'],
            'qa_met_through' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'street1' => ['nullable', 'string', 'max:255'],
            'street2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'total_enrolment' => ['nullable', 'integer', 'min:0'],
            'intl_students_permit' => ['nullable', 'integer', 'min:0'],
            // intl_students_other is retired (no longer relevant to the Act): not asked, never written.
            'in_person_students' => ['nullable', 'integer', 'min:0'],
            'online_students' => ['nullable', 'integer', 'min:0'],
            // enrolment_type is retired too (Total Institution Enrolment's help says FTE or headcount).
        ]);

        DB::table('institutions')->where('crm_id', $institution->crm_id)->update($data);

        app(ProcessNotifier::class)->institutionChanged($institution, $data, $this->portalActor($institution));

        return redirect('/web/institution')->with('success', 'Institution details updated.');
    }

    public function campuses(Request $request): Response
    {
        $institution = $this->institution($request);

        return Inertia::render('Web/Campuses', [
            'institution' => $institution,
            'campuses' => $this->campusesFor($institution),
        ]);
    }

    private function campus(Request $request, string $crmId): ?object
    {
        $institution = $this->institution($request);

        if ($institution === null || ! DB::getSchemaBuilder()->hasTable('campuses')) {
            return null;
        }

        return DB::table('campuses')
            ->where('crm_id', $crmId)
            ->where('institution_crm_id', $institution->crm_id)
            ->first();
    }

    public function createCampus(Request $request): Response
    {
        $institution = $this->institution($request);
        abort_if($institution === null, 403);

        return Inertia::render('Web/EditCampus', [
            'institution' => $institution,
            'campus' => null,
        ]);
    }

    public function editCampus(Request $request, string $crmId): Response
    {
        $campus = $this->campus($request, $crmId);
        abort_if($campus === null, 404);

        return Inertia::render('Web/EditCampus', [
            'institution' => $this->institution($request),
            'campus' => $campus,
        ]);
    }

    public function storeCampus(Request $request): RedirectResponse
    {
        $institution = $this->institution($request);
        abort_if($institution === null, 403);

        $data = $this->validatedCampus($request);

        if ($data['primary_location']) {
            DB::table('campuses')->where('institution_crm_id', $institution->crm_id)->update(['primary_location' => false]);
        }

        DB::table('campuses')->insert(array_merge($data, [
            'crm_id' => (string) Str::uuid(),
            'institution_crm_id' => $institution->crm_id,
            'institution_name' => $institution->name,
        ]));

        app(ProcessNotifier::class)->campusCreated($data, (string) $institution->name, $institution->crm_id, $this->portalActor($institution));

        return redirect('/web/campuses')->with('success', 'Campus '.$data['location_name'].' added.');
    }

    public function updateCampus(Request $request, string $crmId): RedirectResponse
    {
        $campus = $this->campus($request, $crmId);
        abort_if($campus === null, 404);

        $data = $this->validatedCampus($request);

        if ($data['primary_location']) {
            DB::table('campuses')->where('institution_crm_id', $campus->institution_crm_id)->update(['primary_location' => false]);
        }

        DB::table('campuses')->where('crm_id', $crmId)->update($data);

        app(ProcessNotifier::class)->campusChanged($campus, $data, $this->portalActor($this->institution($request)));

        return redirect('/web/campuses')->with('success', 'Campus '.$data['location_name'].' updated.');
    }

    /** @return array<string, mixed> */
    private function validatedCampus(Request $request): array
    {
        $data = $request->validate([
            'location_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'street1' => ['nullable', 'string', 'max:255'],
            'street2' => ['nullable', 'string', 'max:255'],
            'street3' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'primary_location' => ['boolean'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);

        $data['name'] = $data['location_name'];

        return $data;
    }

    public function toggleCampus(Request $request, string $crmId): RedirectResponse
    {
        $campus = $this->campus($request, $crmId);
        abort_if($campus === null, 404);

        $status = $campus->status === 'Active' ? 'Inactive' : 'Active';
        DB::table('campuses')->where('crm_id', $crmId)->update(['status' => $status]);

        return redirect('/web/campuses')->with('success', 'Campus '.($status === 'Active' ? 'reactivated' : 'deactivated').'.');
    }

    public function destroyCampus(Request $request, string $crmId): RedirectResponse
    {
        $campus = $this->campus($request, $crmId);
        abort_if($campus === null, 404);

        DB::table('campuses')->where('crm_id', $crmId)->delete();

        return redirect('/web/campuses')->with('success', 'Campus removed.');
    }

    private function dbasFor(?object $institution)
    {
        if ($institution === null || ! DB::getSchemaBuilder()->hasTable('dbas')) {
            return collect();
        }

        return DB::table('dbas')
            ->where('institution_crm_id', $institution->crm_id)
            ->orderBy('name')
            ->get();
    }

    private function dba(Request $request, string $crmId): ?object
    {
        $institution = $this->institution($request);

        if ($institution === null || ! DB::getSchemaBuilder()->hasTable('dbas')) {
            return null;
        }

        return DB::table('dbas')
            ->where('crm_id', $crmId)
            ->where('institution_crm_id', $institution->crm_id)
            ->first();
    }

    public function dbas(Request $request): Response
    {
        $institution = $this->institution($request);

        return Inertia::render('Web/Dbas', [
            'institution' => $institution,
            'dbas' => $this->dbasFor($institution),
        ]);
    }

    public function createDba(Request $request): Response
    {
        $institution = $this->institution($request);
        abort_if($institution === null, 403);

        return Inertia::render('Web/EditDba', [
            'institution' => $institution,
            'dba' => null,
        ]);
    }

    public function editDba(Request $request, string $crmId): Response
    {
        $dba = $this->dba($request, $crmId);
        abort_if($dba === null, 404);

        return Inertia::render('Web/EditDba', [
            'institution' => $this->institution($request),
            'dba' => $dba,
        ]);
    }

    public function storeDba(Request $request): RedirectResponse
    {
        $institution = $this->institution($request);
        abort_if($institution === null, 403);

        $data = $this->validatedDba($request);

        DB::table('dbas')->insert(array_merge($data, [
            'crm_id' => (string) Str::uuid(),
            'institution_crm_id' => $institution->crm_id,
            'institution_name' => $institution->name,
        ]));

        app(ProcessNotifier::class)->dbaCreated($data, (string) $institution->name, $institution->crm_id, $this->portalActor($institution));

        return redirect('/web/dbas')->with('success', 'DBA '.$data['name'].' added.');
    }

    public function updateDba(Request $request, string $crmId): RedirectResponse
    {
        $dba = $this->dba($request, $crmId);
        abort_if($dba === null, 404);

        $data = $this->validatedDba($request);
        DB::table('dbas')->where('crm_id', $crmId)->update($data);

        app(ProcessNotifier::class)->dbaChanged($dba, $data, $this->portalActor($this->institution($request)));

        return redirect('/web/dbas')->with('success', 'DBA '.$data['name'].' updated.');
    }

    public function toggleDba(Request $request, string $crmId): RedirectResponse
    {
        $dba = $this->dba($request, $crmId);
        abort_if($dba === null, 404);

        $status = $dba->status === 'Active' ? 'Inactive' : 'Active';
        DB::table('dbas')->where('crm_id', $crmId)->update(['status' => $status]);

        return redirect('/web/dbas')->with('success', 'DBA '.($status === 'Active' ? 'reactivated' : 'deactivated').'.');
    }

    public function destroyDba(Request $request, string $crmId): RedirectResponse
    {
        $dba = $this->dba($request, $crmId);
        abort_if($dba === null, 404);

        DB::table('dbas')->where('crm_id', $crmId)->delete();

        return redirect('/web/dbas')->with('success', 'DBA removed.');
    }

    /** @return array<string, mixed> */
    private function validatedDba(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'street1' => ['nullable', 'string', 'max:255'],
            'street2' => ['nullable', 'string', 'max:255'],
            'street3' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);
    }

    public function users(Request $request): Response
    {
        $institution = $this->institution($request);
        $currentUser = Auth::user();

        return Inertia::render('Web/Users', [
            'institution' => $institution,
            'users' => $this->usersFor($institution),
            'canManage' => $currentUser !== null && $currentUser->hasAnyRole([Role::INSTITUTION_ADMIN, Role::SUPER_ADMIN]),
            'currentUserId' => $currentUser?->id,
        ]);
    }

    /**
     * Institution-admin only: switch a staff member's portal access role
     * (Admin / User / Guest). Institution users cannot otherwise edit staff
     * details, mirroring the FSG StaffList behaviour.
     */
    public function updateStaffRole(Request $request, int $userId): RedirectResponse
    {
        $institution = $this->institution($request);
        abort_if($institution === null, 403);

        $actor = Auth::user();
        abort_unless($actor !== null && $actor->hasAnyRole([Role::INSTITUTION_ADMIN, Role::SUPER_ADMIN]), 403);

        // An admin cannot change their own role (prevents self lock-out).
        abort_if($actor->id === $userId, 403);

        $data = $request->validate(['role' => ['required', 'in:Admin,User,Guest']]);

        $target = User::with('roles')->find($userId);
        abort_if($target === null, 404);
        abort_unless($this->accountBelongsToInstitution($target, $institution), 403);

        $newRole = match ($data['role']) {
            'Admin' => Role::INSTITUTION_ADMIN,
            'User' => Role::INSTITUTION_USER,
            default => Role::INSTITUTION_GUEST,
        };

        $role = Role::firstOrCreate(['name' => $newRole]);
        $target->roles()->detach(
            Role::whereIn('name', [Role::INSTITUTION_ADMIN, Role::INSTITUTION_USER, Role::INSTITUTION_GUEST])->pluck('id')
        );
        $target->roles()->attach($role->id);

        return redirect('/web/users')->with('success', 'Staff role updated.');
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $institution = $this->institution($request);
        abort_if($institution === null, 403);

        if ($this->hasActiveApplication($institution)) {
            return redirect('/web/applications')
                ->with('error', 'You already have an application that is submitted or under review. You cannot start a new one until it is finalized.');
        }

        return Inertia::render('Web/NewApplication', [
            'institution' => $institution,
            'previousNames' => app(ApplicationProfileService::class)->recentInstitutionNames($institution->crm_id),
            'websiteCompliance' => app(WebsiteComplianceService::class)->formChecklist(null, $institution),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institution = $this->institution($request);
        abort_if($institution === null, 403);

        if ($this->hasActiveApplication($institution)) {
            return redirect('/web/applications')
                ->with('error', 'You already have an application that is submitted or under review. You cannot submit a new one until it is finalized.');
        }

        $compliance = app(WebsiteComplianceService::class);
        $checklist = $compliance->formChecklist(null, $institution);
        $submitting = $this->submitting($request);
        $data = $this->validatedApplication($request, $checklist, $submitting);
        $previousNames = Arr::pull($data, 'previous_names') ?? [];
        $campusLocations = Arr::pull($data, 'campus_locations') ?? [];
        $complianceAnswers = Arr::pull($data, 'website_compliance') ?? [];
        $fees = app(ApplicationFeeService::class);

        $reference = 'APP-'.str_pad((string) $fees->nextNumber('applications', 'reference'), 8, '0', STR_PAD_LEFT);
        $feeType = app(ProcessNotifier::class)->isReapplication($institution)
            ? ApplicationFeeService::FEE_APPLICATION_RENEWAL
            : ApplicationFeeService::FEE_APPLICATION_NEW;
        $amount = (float) config('eqa.fees.'.$feeType);
        if ($fees->isFeeExempt($institution)) {
            $amount = 0.0;
        }
        $crmId = (string) Str::uuid();

        // Saved as a Draft; the application fee is invoiced once it is submitted.
        DB::transaction(function () use ($data, $crmId, $reference, $institution, $amount, $previousNames, $campusLocations, $compliance, $checklist, $complianceAnswers): void {
            DB::table('applications')->insert(array_merge($data, [
                'crm_id' => $crmId,
                'reference' => $reference,
                'status' => 'Draft',
                'workflow_stage' => 'draft',
                'institution_crm_id' => $institution->crm_id,
                'institution_name' => $institution->name,
                'owner_name' => $institution->business_owner ?? null,
                'application_date' => Carbon::now()->toDateString(),
                'application_fee' => $amount,
                'total_due' => $amount,
            ]));
            app(ApplicationProfileService::class)->syncPreviousNames($crmId, $institution->crm_id, $previousNames);
            app(ApplicationProfileService::class)->syncCampusLocations($crmId, $campusLocations);
            $compliance->sync($crmId, $checklist, $complianceAnswers);
        });

        if ($submitting) {
            return $this->submitApplication(DB::table('applications')->where('crm_id', $crmId)->first());
        }

        return redirect($this->stepUrl($crmId, $request))
            ->with('success', 'Application '.$reference.' saved as a draft. Continue through the steps and select Submit Application when it is complete.');
    }

    public function edit(Request $request, string $crmId): Response
    {
        $application = $this->ownApplication($request, $crmId);
        abort_unless(app(ApplicationFeeService::class)->editableByInstitution($application), 403);
        $institution = $this->institution($request);

        return Inertia::render('Web/NewApplication', [
            'institution' => $institution,
            'application' => $application,
            'campusLocations' => app(ApplicationProfileService::class)->campusLocations($application->crm_id),
            'previousNames' => app(ApplicationProfileService::class)->previousNames($application->crm_id),
            'websiteCompliance' => app(WebsiteComplianceService::class)->formChecklist($application, $institution),
        ]);
    }

    public function update(Request $request, string $crmId): RedirectResponse
    {
        $application = $this->ownApplication($request, $crmId);
        abort_unless(app(ApplicationFeeService::class)->editableByInstitution($application), 403);

        $compliance = app(WebsiteComplianceService::class);
        $checklist = $compliance->formChecklist($application, $this->institution($request));
        $submitting = $this->submitting($request);
        $data = $this->validatedApplication($request, $checklist, $submitting);
        $previousNames = Arr::pull($data, 'previous_names') ?? [];
        $campusLocations = Arr::pull($data, 'campus_locations') ?? [];
        $complianceAnswers = Arr::pull($data, 'website_compliance') ?? [];
        DB::transaction(function () use ($crmId, $data, $previousNames, $campusLocations, $application, $compliance, $checklist, $complianceAnswers): void {
            DB::table('applications')->where('crm_id', $crmId)->update($data);
            app(ApplicationProfileService::class)->syncPreviousNames($crmId, (string) $application->institution_crm_id, $previousNames);
            app(ApplicationProfileService::class)->syncCampusLocations($crmId, $campusLocations);
            $compliance->sync($crmId, $checklist, $complianceAnswers);
        });

        if ($submitting) {
            return $this->submitApplication(DB::table('applications')->where('crm_id', $crmId)->first());
        }

        return redirect($this->stepUrl($crmId, $request))->with('success', 'Application '.$application->reference.' saved.');
    }

    /**
     * Submit (or resubmit) from the application page: every step must be complete, otherwise the
     * institution is taken back to the form with the missing information highlighted.
     */
    public function submit(Request $request, string $crmId): RedirectResponse
    {
        $application = $this->ownApplication($request, $crmId);
        abort_unless(in_array($application->workflow_stage, ['draft', 'changes_requested'], true), 403);

        $checklist = app(WebsiteComplianceService::class)->formChecklist($application, $this->institution($request));
        $validator = Validator::make(
            $this->savedApplicationData($application, $checklist),
            $this->applicationRules($checklist, true),
            $this->applicationMessages(),
        );
        if ($validator->fails()) {
            return redirect('/web/applications/'.$crmId.'/edit')
                ->withErrors($validator)
                ->with('error', 'The application is not complete yet. Complete the highlighted steps, then select Submit Application.');
        }

        return $this->submitApplication($application);
    }

    /** Submit a draft (the application fee is then invoiced), or resubmit requested additional information. */
    private function submitApplication(object $application): RedirectResponse
    {
        $crmId = (string) $application->crm_id;
        $fees = app(ApplicationFeeService::class);
        $firstSubmission = $fees->awaitingSubmission($application);
        $fees->submit($application);

        $message = 'Application '.$application->reference.' submitted.';
        $invoice = $fees->invoiceFor($crmId, ApplicationFeeService::APPLICATION_FEES);
        if ($firstSubmission && $invoice !== null && $invoice->invoice_status !== 'Paid') {
            $message .= ' Please pay the application fee (invoice '.$invoice->invoice_number.').';
        }

        return redirect('/web/applications/'.$crmId)->with('success', $message);
    }

    public function appeal(Request $request, string $crmId): RedirectResponse
    {
        $application = $this->ownApplication($request, $crmId);
        $fees = app(ApplicationFeeService::class);

        if (! $fees->canAppeal($application)) {
            return redirect('/web/applications/'.$crmId)->with('error', 'This decision can no longer be appealed.');
        }

        $invoice = $fees->openAppeal($application);
        if ($invoice->invoice_status === 'Paid') {
            return redirect('/web/applications/'.$crmId)
                ->with('success', 'Appeal submitted. No appeal fee applies because your institution is fee exempt.');
        }

        return redirect('/web/applications/'.$crmId)
            ->with('success', 'Appeal started. Pay the appeal fee (invoice '.$invoice->invoice_number.') to submit it.');
    }

    private function ownApplication(Request $request, string $crmId): object
    {
        $institution = $this->institution($request);
        abort_if($institution === null, 403);

        $application = DB::table('applications')
            ->where('crm_id', $crmId)
            ->where('institution_crm_id', $institution->crm_id)
            ->first();
        abort_if($application === null, 404);

        return $application;
    }

    /**
     * Rules for the application form. Saving a draft ($complete false) only checks what has been entered,
     * so the institution can save any step at any time; submitting ($complete true) also requires every step.
     *
     * @param  list<array<string, mixed>>  $complianceChecklist  the Website Compliance checklist this application answers
     * @return array<string, mixed>
     */
    private function applicationRules(array $complianceChecklist, bool $complete): array
    {
        $required = $complete ? 'required' : 'nullable';
        $today = Carbon::now('America/Vancouver')->toDateString();
        $lookbackStart = app(ApplicationProfileService::class)->lookbackStart();

        $rules = [
            'total_enrolment' => ['nullable', 'integer', 'min:0'],
            'intl_students_permit' => ['nullable', 'integer', 'min:0'],
            // intl_students_other is retired (no longer relevant to the Act): not asked, never written.
            'in_person_students' => ['nullable', 'integer', 'min:0'],
            'online_students' => ['nullable', 'integer', 'min:0'],
            // enrolment_type is retired too (Total Institution Enrolment's help says FTE or headcount).
            'per_student_fee_enrolment' => [$required, 'integer', 'min:0', 'max:1000000'],
            'brand_usage_plan' => ['nullable', 'string', 'max:2000'],
            'other_logos_trademarks' => ['nullable', 'string', 'max:2000'],
            'affiliates_partners' => ['nullable', 'string', 'max:2000'],
            'representative_signature' => [$required, 'string', 'max:255'],
            // Campus locations entered on this application (not the institution's campuses; no status).
            'campus_locations' => array_merge($complete ? ['required', 'array', 'min:1'] : ['nullable', 'array'], ['max:50', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_array($value) && count(array_filter($value, fn ($row): bool => is_array($row) && filter_var($row['primary_location'] ?? false, FILTER_VALIDATE_BOOLEAN))) > 1) {
                    $fail('Only one campus location can be the primary location.');
                }
            }]),
            'campus_locations.*' => ['array'],
            'campus_locations.*.location_name' => ['required', 'string', 'max:255'],
            'campus_locations.*.email' => ['nullable', 'email', 'max:255'],
            'campus_locations.*.website' => ['nullable', 'string', 'max:255'],
            'campus_locations.*.street1' => ['required', 'string', 'max:255'],
            'campus_locations.*.street2' => ['nullable', 'string', 'max:255'],
            'campus_locations.*.city' => ['required', 'string', 'max:255'],
            'campus_locations.*.province' => ['required', 'string', 'max:100'],
            'campus_locations.*.postal_code' => ['required', 'string', 'max:20'],
            'campus_locations.*.country' => ['nullable', 'string', 'max:100'],
            'campus_locations.*.primary_location' => ['nullable', 'boolean'],
            // Address for service: prefilled from the institution profile, confirmed per application.
            'service_street1' => [$required, 'string', 'max:255'],
            'service_street2' => ['nullable', 'string', 'max:255'],
            'service_city' => [$required, 'string', 'max:255'],
            'service_province' => [$required, 'string', 'max:100'],
            'service_postal_code' => [$required, 'string', 'max:20'],
            'service_country' => [$required, 'string', 'max:100'],
            'service_address_confirmed' => $complete ? ['accepted'] : ['nullable', 'boolean'],
            // Operating names used in the last 5 years (stored in previous_operating_names).
            'previous_names' => ['nullable', 'array', 'max:20'],
            'previous_names.*' => ['array', function (string $attribute, mixed $value, Closure $fail) use ($lookbackStart, $today): void {
                $end = is_array($value) ? (($value['used_to'] ?? null) ?: $today) : $today;
                if (is_string($end) && $end < $lookbackStart) {
                    $fail('Only list names used in the last '.ApplicationProfileService::NAME_LOOKBACK_YEARS.' years.');
                }
            }],
            'previous_names.*.name' => ['required', 'string', 'max:255'],
            'previous_names.*.used_from' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'previous_names.*.used_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:previous_names.*.used_from', 'before_or_equal:'.$today],
        ];
        foreach (self::APPLICATION_FLAGS as $flag) {
            $rules[$flag] = ['nullable', 'boolean'];
        }
        foreach (self::APPLICATION_AFFIRMATIONS as $affirmation) {
            $rules[$affirmation] = $complete ? ['accepted'] : ['nullable', 'boolean'];
        }

        // Website Compliance: a link and/or a comment for every item on the application's checklist.
        return $rules + app(WebsiteComplianceService::class)->rules($complianceChecklist, $complete);
    }

    /** @return array<string, string> */
    private function applicationMessages(): array
    {
        return app(WebsiteComplianceService::class)->messages() + [
            'campus_locations.required' => 'Add at least one campus location.',
            'campus_locations.min' => 'Add at least one campus location.',
            'campus_locations.*.location_name.required' => 'Enter the location name.',
            'campus_locations.*.street1.required' => 'Enter the street address.',
            'campus_locations.*.city.required' => 'Enter the city.',
            'campus_locations.*.province.required' => 'Enter the province.',
            'campus_locations.*.postal_code.required' => 'Enter the postal code.',
            'campus_locations.*.email.email' => 'Enter a valid email address.',
            'service_street1.required' => 'Enter the street address for service.',
            'service_city.required' => 'Enter the city for service.',
            'service_province.required' => 'Enter the province for service.',
            'service_postal_code.required' => 'Enter the postal code for service.',
            'service_country.required' => 'Enter the country for service.',
            'service_address_confirmed.accepted' => 'Confirm the address for service.',
            'per_student_fee_enrolment.required' => 'Enter the number of international students for the per student fee.',
            'per_student_fee_enrolment.integer' => 'Enter a whole number.',
            'representative_signature.required' => 'Enter the institutional representative signature.',
            'accepted' => 'Confirm this affirmation.',
            'previous_names.*.name.required' => 'Enter the previous operating name.',
            'previous_names.*.used_from.required' => 'Enter the date the name was first used.',
            'previous_names.*.used_from.before_or_equal' => 'The date first used cannot be in the future.',
            'previous_names.*.used_to.after_or_equal' => 'The date last used must be on or after the date first used.',
            'previous_names.*.used_to.before_or_equal' => 'The date last used cannot be in the future.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $complianceChecklist
     * @return array<string, mixed>
     */
    private function validatedApplication(Request $request, array $complianceChecklist, bool $complete): array
    {
        $data = $request->validate($this->applicationRules($complianceChecklist, $complete), $this->applicationMessages());
        foreach (array_merge(self::APPLICATION_FLAGS, self::APPLICATION_AFFIRMATIONS, ['service_address_confirmed']) as $field) {
            $data[$field] = $request->boolean($field);
        }

        return $data;
    }

    /**
     * Everything saved on the application, in the form's shape, to check it is complete before submitting.
     *
     * @param  list<array<string, mixed>>  $complianceChecklist  with the saved answers
     * @return array<string, mixed>
     */
    private function savedApplicationData(object $application, array $complianceChecklist): array
    {
        $profile = app(ApplicationProfileService::class);
        $data = (array) $application;
        $data['campus_locations'] = $profile->campusLocations((string) $application->crm_id);
        $data['previous_names'] = $profile->previousNames((string) $application->crm_id);
        $data['website_compliance'] = [];
        foreach ($complianceChecklist as $section) {
            foreach ($section['items'] as $item) {
                $data['website_compliance'][$item['key']] = ['url' => $item['url'], 'comment' => $item['comment']];
            }
        }

        return $data;
    }

    /** "Submit Application" on the form (rather than "Save Draft"). */
    private function submitting(Request $request): bool
    {
        return $request->input('intent') === 'submit';
    }

    /** After saving a draft, return to the step the institution was on. */
    private function stepUrl(string $crmId, Request $request): string
    {
        return '/web/applications/'.$crmId.'/edit?step='.max(1, min(self::APPLICATION_STEPS, (int) $request->input('step', 1)));
    }

    /** Human label for the acting institution portal user, for change notifications. */
    private function portalActor(?object $institution): string
    {
        $name = trim((string) ($institution->name ?? ''));

        return $name !== '' ? $name.' (institution portal)' : 'Institution portal';
    }
}
