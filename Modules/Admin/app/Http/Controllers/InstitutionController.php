<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Services\ApplicationFeeService;
use Modules\Admin\Services\BceidService;
use Modules\Admin\Services\DesignationService;
use Modules\Admin\Services\ProcessNotifier;
use RuntimeException;

/**
 * Ministry (IDIR) admin view of a single institution, mirroring the legacy
 * Dynamics InstitutionView: the profile plus its Locations (campuses) and
 * Contacts (institution users). The admin can add, edit, deactivate and
 * remove those child records for any institution. Reuses the Web module's
 * EditCampus / EditUser forms via explicit submit/cancel URLs.
 */
class InstitutionController extends Controller
{
    private function institution(string $crmId): ?object
    {
        return Schema::hasTable('institutions')
            ? DB::table('institutions')->where('crm_id', $crmId)->first()
            : null;
    }

    public function show(string $crmId): Response
    {
        $institution = $this->institution($crmId);
        abort_if($institution === null, 404);

        return Inertia::render('Admin/InstitutionView', [
            'institution' => $institution,
            'designationOptions' => [
                'statuses' => DesignationService::EQA_STATUSES,
                'standings' => DesignationService::STANDINGS,
                'ptibRequired' => DesignationService::ptiruRequired($institution->qa_met_through),
                'ptibQaMetThrough' => DesignationService::PTIRU_QA_MET_THROUGH,
            ],
            'applications' => Schema::hasTable('applications')
                ? DB::table('applications')->where('institution_crm_id', $crmId)->orderByDesc('application_date')->get()
                : collect(),
            'campuses' => Schema::hasTable('campuses')
                ? DB::table('campuses')->where('institution_crm_id', $crmId)->orderByDesc('primary_location')->orderBy('name')->get()
                : collect(),
            'users' => $this->institutionStaff($crmId),
            'dbas' => Schema::hasTable('dbas')
                ? DB::table('dbas')->where('institution_crm_id', $crmId)->orderBy('name')->get()
                : collect(),
            'sentEmails' => Schema::hasTable('sent_emails')
                ? DB::table('sent_emails')->where('institution_crm_id', $crmId)->orderByDesc('sent_at')->orderByDesc('id')->get()
                : collect(),
            'bceidLookup' => session($this->bceidLookupKey($crmId)),
        ]);
    }

    // --- Institution creation -------------------------------------------

    public function createInstitution(): Response
    {
        return Inertia::render('Admin/NewInstitution');
    }

    public function storeInstitution(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['required', 'string', 'max:255'],
            'bc_incorporation_number' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'string', 'max:255'],
            'street1' => ['required', 'string', 'max:255'],
            'street2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:100'],
        ]);

        $crmId = (string) Str::uuid();
        DB::table('institutions')->insert(array_merge($data, [
            'crm_id' => $crmId,
            'institution_number' => $this->nextInstitutionNumber(),
            // Placeholder until the real BCeID business is linked via "Fetch BCeID data".
            'bceid_business_guid' => $this->uniqueBusinessGuid(),
            // Set explicitly so no database column default can mark a new institution as designated or de-designated.
            'eqa_status' => 'Pending',
        ]));

        return redirect("/admin/institutions/{$crmId}")->with('success', 'Institution created.');
    }

    // --- Institution details --------------------------------------------

    public function edit(string $crmId): Response
    {
        $institution = $this->institution($crmId);
        abort_if($institution === null, 404);

        return Inertia::render('Web/EditInstitution', [
            'institution' => $institution,
            'qaOptions' => [
                'Private Training Institutions Regulatory Unit (PTIRU) Designation',
                'Public Institution Legislation',
                'Ministry Review Process',
                'Ministers consent under the Degree Authorization Act (DAA)',
                'Languages Canada Accreditation',
            ],
            'enrolmentTypes' => ['FTE', 'Enrolment'],
            'canEditDli' => true,
            'submitUrl' => "/admin/institutions/{$crmId}",
            'cancelUrl' => "/admin/institutions/{$crmId}",
        ]);
    }

    public function update(Request $request, string $crmId): RedirectResponse
    {
        $institution = $this->institution($crmId);
        abort_if($institution === null, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'bc_incorporation_number' => ['nullable', 'string', 'max:100'],
            'dli_number' => ['nullable', 'string', 'max:100'],
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
            'intl_students_other' => ['nullable', 'integer', 'min:0'],
            'in_person_students' => ['nullable', 'integer', 'min:0'],
            'online_students' => ['nullable', 'integer', 'min:0'],
            'enrolment_type' => ['nullable', 'string', 'max:50'],
        ]);

        DB::table('institutions')->where('crm_id', $crmId)->update($data);

        // Mirror the legacy InstitutionNotificationEmail plugin: when key data
        // fields change, notify the EQA mailbox with a diff. Gated by the global
        // master switch (default OFF), so this is a no-op until enabled.
        app(ProcessNotifier::class)->institutionChanged($institution, $data, $this->adminActor());

        // Changing QA Met Through can invalidate an existing Designated status
        // (PTIRU Standing only gates designation under the PTIRU pathway).
        app(DesignationService::class)->reconcileAfterQaChange($crmId);

        return redirect("/admin/institutions/{$crmId}")->with('success', 'Institution details updated.');
    }

    /**
     * Human label for the acting ministry (IDIR) user, for change notifications.
     */
    private function adminActor(): string
    {
        return (string) (request()->user()->name ?? 'Ministry user');
    }

    /**
     * Ministry sets the institution's Designated status (EQA Status), EQA / PTIRU
     * Standing and designation dates. Applies the coupled business rules and
     * cascades the standing onto the institution's open applications.
     */
    public function updateDesignation(Request $request, string $crmId): RedirectResponse
    {
        $institution = $this->institution($crmId);
        abort_if($institution === null, 404);

        // PTIRU Standing is required and gates designation only when this
        // institution's QA is met through PTIRU Designation.
        $ptibRequired = DesignationService::ptiruRequired($institution->qa_met_through);

        $validator = Validator::make($request->all(), [
            'eqa_status' => ['required', Rule::in(DesignationService::EQA_STATUSES)],
            'eqa_standing' => ['nullable', Rule::in(DesignationService::STANDINGS)],
            'ptib_standing' => [$ptibRequired ? 'required' : 'nullable', Rule::in(DesignationService::STANDINGS)],
            'designation_start' => ['nullable', 'date'],
            'designation_expiry' => ['nullable', 'date'],
        ], [
            'ptib_standing.required' => 'PTIRU Standing is required when QA is met through PTIRU Designation.',
        ]);

        $validator->after(function ($validator) use ($request, $institution): void {
            if ($request->input('eqa_status') === 'Designated'
                && ! DesignationService::ptiruPermitsDesignation($institution->qa_met_through, $request->input('ptib_standing'))) {
                $validator->errors()->add(
                    'eqa_status',
                    'EQA Status cannot be Designated because QA is met through PTIRU Designation and PTIRU Standing is not In Good Standing.'
                );
            }
        });

        $data = $validator->validate();

        app(DesignationService::class)->applyInstitutionDesignation($crmId, $data);

        return redirect("/admin/institutions/{$crmId}")->with('success', 'Designation status updated.');
    }

    /**
     * Ministry Admin marks the institution fee exempt (e.g. an Indigenous institution exempt under
     * the Act): every fee (application, renewal, administrative, appeal) is $0, and any invoice it
     * still owes is waived.
     */
    public function updateFeeExempt(Request $request, string $crmId): RedirectResponse
    {
        $institution = $this->institution($crmId);
        abort_if($institution === null, 404);

        $exempt = (bool) $request->validate(['fee_exempt' => ['required', 'boolean']])['fee_exempt'];
        DB::table('institutions')->where('crm_id', $crmId)->update(['fee_exempt' => $exempt]);

        // Exemption is legislated by institution type, so whatever it still owes is waived too.
        $counts = $exempt
            ? app(ApplicationFeeService::class)->waiveOutstandingInvoices($crmId)
            : ['waived' => 0, 'skipped' => 0];

        Log::info('Institution fee exemption '.($exempt ? 'granted' : 'removed'), [
            'institution_crm_id' => $crmId,
            'by' => $this->adminActor(),
            'invoices_waived' => $counts['waived'],
            'invoices_skipped' => $counts['skipped'],
        ]);

        if (! $exempt) {
            return redirect("/admin/institutions/{$crmId}")->with('success', 'Fee exemption removed. Standard fees apply to new invoices.');
        }

        $message = 'Institution marked fee exempt. No fees will be charged';
        $message .= $counts['waived'] > 0
            ? '; '.$counts['waived'].' outstanding invoice'.($counts['waived'] === 1 ? ' was' : 's were').' waived.'
            : '.';
        if ($counts['skipped'] > 0) {
            $message .= ' '.$counts['skipped'].' invoice'.($counts['skipped'] === 1 ? ' has' : 's have').' an Express Pay payment under review and '.($counts['skipped'] === 1 ? 'was' : 'were').' not waived.';
        }

        return redirect("/admin/institutions/{$crmId}")->with('success', $message);
    }

    // --- Locations (campuses) ------------------------------------------

    private function campus(string $crmId, string $campusId): ?object
    {
        return Schema::hasTable('campuses')
            ? DB::table('campuses')->where('crm_id', $campusId)->where('institution_crm_id', $crmId)->first()
            : null;
    }

    public function createCampus(string $crmId): Response
    {
        $institution = $this->institution($crmId);
        abort_if($institution === null, 404);

        return Inertia::render('Web/EditCampus', [
            'institution' => $institution,
            'campus' => null,
            'submitUrl' => "/admin/institutions/{$crmId}/campuses",
            'method' => 'post',
            'cancelUrl' => "/admin/institutions/{$crmId}",
        ]);
    }

    public function editCampus(string $crmId, string $campusId): Response
    {
        $campus = $this->campus($crmId, $campusId);
        abort_if($campus === null, 404);

        return Inertia::render('Web/EditCampus', [
            'institution' => $this->institution($crmId),
            'campus' => $campus,
            'submitUrl' => "/admin/institutions/{$crmId}/campuses/{$campusId}",
            'method' => 'put',
            'cancelUrl' => "/admin/institutions/{$crmId}",
        ]);
    }

    public function storeCampus(Request $request, string $crmId): RedirectResponse
    {
        $institution = $this->institution($crmId);
        abort_if($institution === null, 404);

        $data = $this->validatedCampus($request);

        if ($data['primary_location']) {
            DB::table('campuses')->where('institution_crm_id', $crmId)->update(['primary_location' => false]);
        }

        DB::table('campuses')->insert(array_merge($data, [
            'crm_id' => (string) Str::uuid(),
            'institution_crm_id' => $crmId,
            'institution_name' => $institution->name,
        ]));

        app(ProcessNotifier::class)->campusCreated($data, (string) $institution->name, $crmId, $this->adminActor());

        return redirect("/admin/institutions/{$crmId}")->with('success', 'Location added.');
    }

    public function updateCampus(Request $request, string $crmId, string $campusId): RedirectResponse
    {
        $campus = $this->campus($crmId, $campusId);
        abort_if($campus === null, 404);

        $data = $this->validatedCampus($request);

        if ($data['primary_location']) {
            DB::table('campuses')->where('institution_crm_id', $crmId)->update(['primary_location' => false]);
        }

        DB::table('campuses')->where('crm_id', $campusId)->update($data);

        app(ProcessNotifier::class)->campusChanged($campus, $data, $this->adminActor());

        return redirect("/admin/institutions/{$crmId}")->with('success', 'Location updated.');
    }

    public function toggleCampus(string $crmId, string $campusId): RedirectResponse
    {
        $campus = $this->campus($crmId, $campusId);
        abort_if($campus === null, 404);

        $status = $campus->status === 'Active' ? 'Inactive' : 'Active';
        DB::table('campuses')->where('crm_id', $campusId)->update(['status' => $status]);

        return redirect("/admin/institutions/{$crmId}")->with('success', 'Location '.($status === 'Active' ? 'reactivated' : 'deactivated').'.');
    }

    public function destroyCampus(string $crmId, string $campusId): RedirectResponse
    {
        $campus = $this->campus($crmId, $campusId);
        abort_if($campus === null, 404);

        DB::table('campuses')->where('crm_id', $campusId)->delete();

        return redirect("/admin/institutions/{$crmId}")->with('success', 'Location removed.');
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

    // --- Contacts (institution users) ----------------------------------

    private function user(string $crmId, string $userId): ?object
    {
        return Schema::hasTable('institution_users')
            ? DB::table('institution_users')->where('crm_id', $userId)->where('institution_crm_id', $crmId)->first()
            : null;
    }

    public function createUser(string $crmId): Response
    {
        $institution = $this->institution($crmId);
        abort_if($institution === null, 404);

        return Inertia::render('Web/EditUser', [
            'institution' => $institution,
            'user' => null,
            'submitUrl' => "/admin/institutions/{$crmId}/users",
            'method' => 'post',
            'cancelUrl' => "/admin/institutions/{$crmId}",
        ]);
    }

    public function editUser(string $crmId, string $userId): Response
    {
        $user = $this->user($crmId, $userId);
        abort_if($user === null, 404);

        return Inertia::render('Web/EditUser', [
            'institution' => $this->institution($crmId),
            'user' => $user,
            'submitUrl' => "/admin/institutions/{$crmId}/users/{$userId}",
            'method' => 'put',
            'cancelUrl' => "/admin/institutions/{$crmId}",
        ]);
    }

    public function storeUser(Request $request, string $crmId): RedirectResponse
    {
        $institution = $this->institution($crmId);
        abort_if($institution === null, 404);

        $data = $this->validatedUser($request);

        DB::table('institution_users')->insert(array_merge($data, [
            'crm_id' => (string) Str::uuid(),
            'institution_crm_id' => $crmId,
            'institution_name' => $institution->name,
        ]));

        app(ProcessNotifier::class)->portalUserCreated($data, $institution);

        return redirect("/admin/institutions/{$crmId}")->with('success', 'Contact added.');
    }

    public function updateUser(Request $request, string $crmId, string $userId): RedirectResponse
    {
        $user = $this->user($crmId, $userId);
        abort_if($user === null, 404);

        $data = $this->validatedUser($request);

        DB::table('institution_users')->where('crm_id', $userId)->update($data);

        return redirect("/admin/institutions/{$crmId}")->with('success', 'Contact updated.');
    }

    public function toggleUser(string $crmId, string $userId): RedirectResponse
    {
        $user = $this->user($crmId, $userId);
        abort_if($user === null, 404);

        $active = ! ($user->web_user_active === true || $user->web_user_active === 't' || $user->web_user_active === 1 || $user->web_user_active === '1');
        DB::table('institution_users')->where('crm_id', $userId)->update(['web_user_active' => $active]);

        return redirect("/admin/institutions/{$crmId}")->with('success', 'Contact '.($active ? 'reactivated' : 'deactivated').'.');
    }

    public function destroyUser(string $crmId, string $userId): RedirectResponse
    {
        $user = $this->user($crmId, $userId);
        abort_if($user === null, 404);

        DB::table('institution_users')->where('crm_id', $userId)->delete();

        return redirect("/admin/institutions/{$crmId}")->with('success', 'Contact removed.');
    }

    /** @return array<string, mixed> */
    private function validatedUser(Request $request): array
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'role' => ['nullable', 'string', 'max:50'],
            'web_user_name' => ['nullable', 'string', 'max:255'],
            'web_user_active' => ['boolean'],
        ]);

        $data['full_name'] = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: null;

        return $data;
    }

    // --- Institution staff (BCeID user accounts) -----------------------

    private const INSTITUTION_ROLES = [Role::INSTITUTION_ADMIN, Role::INSTITUTION_USER, Role::INSTITUTION_GUEST];

    /**
     * The institution's contacts, each joined to its BCeID user account (matched
     * on guid or email) so the grid can show and toggle the account's role and
     * active status. Run `institution-users:migrate` to seed the accounts.
     *
     * @return Collection<int, object>
     */
    private function institutionStaff(string $crmId): Collection
    {
        if (! Schema::hasTable('institution_users')) {
            return collect();
        }

        $contacts = DB::table('institution_users')
            ->where('institution_crm_id', $crmId)
            ->orderBy('full_name')
            ->get();

        $guids = $contacts->pluck('bceid_user_guid')->filter()->map(fn ($g) => Str::lower($g))->all();
        $emails = $contacts->pluck('email')->filter()->map(fn ($e) => Str::lower($e))->all();

        $accounts = User::with('roles')
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
            $contact->bceid_username = $contact->bceid_username ?: $account?->bceid_username;
            $contact->access_type = $account === null ? null
                : ($roles->contains(Role::INSTITUTION_ADMIN) ? 'Admin'
                    : ($roles->contains(Role::INSTITUTION_GUEST) ? 'Guest' : 'User'));
            $contact->account_disabled = $account ? (bool) $account->disabled : null;

            return $contact;
        });
    }

    private function accountMatchesContact(User $user, object $contact): bool
    {
        // Strong match: the BCeID User GUID (unique per BCeID account).
        if (! empty($contact->bceid_user_guid) && ! empty($user->bceid_user_guid)
            && Str::lower($user->bceid_user_guid) === Str::lower($contact->bceid_user_guid)) {
            return true;
        }

        // Strong match: the BCeID logon.
        if (! empty($contact->bceid_username) && ! empty($user->bceid_username)
            && Str::upper($user->bceid_username) === Str::upper($contact->bceid_username)) {
            return true;
        }

        // Fallback to email ONLY when neither side has a BCeID identity — legacy/test
        // data reuses placeholder emails across many contacts, so matching a BCeID
        // account by a shared email would attach the wrong person.
        $contactHasBceid = ! empty($contact->bceid_username) || ! empty($contact->bceid_user_guid);
        $userHasBceid = ! empty($user->bceid_username) || ! empty($user->bceid_user_guid);

        return ! $contactHasBceid && ! $userHasBceid
            && ! empty($contact->email) && ! empty($user->email)
            && Str::lower($user->email) === Str::lower($contact->email);
    }

    public function updateStaffRole(Request $request, string $crmId, User $user): RedirectResponse
    {
        abort_if($this->institution($crmId) === null, 404);
        $data = $request->validate(['role' => ['required', 'in:Admin,User,Guest']]);

        $newRole = match ($data['role']) {
            'Admin' => Role::INSTITUTION_ADMIN,
            'User' => Role::INSTITUTION_USER,
            default => Role::INSTITUTION_GUEST,
        };

        $role = Role::firstOrCreate(['name' => $newRole]);
        $user->roles()->detach(Role::whereIn('name', self::INSTITUTION_ROLES)->pluck('id'));
        $user->roles()->attach($role->id);

        return redirect("/admin/institutions/{$crmId}")->with('success', 'Staff role updated.');
    }

    public function updateStaffStatus(Request $request, string $crmId, User $user): RedirectResponse
    {
        abort_if($this->institution($crmId) === null, 404);
        $data = $request->validate(['disabled' => ['required', 'boolean']]);

        $user->update(['disabled' => $data['disabled']]);

        return redirect("/admin/institutions/{$crmId}")->with('success', 'Staff status updated.');
    }

    /**
     * Ministry "login as" impersonation: switch the current session from the
     * ministry admin to the selected institution staff account and drop them
     * into that institution's web portal. The original admin is remembered in
     * the session so they can return via stopImpersonating().
     */
    public function loginAsStaff(Request $request, string $crmId, int $userId): RedirectResponse
    {
        $institution = $this->institution($crmId);
        abort_if($institution === null, 404);

        $target = User::with('roles')->find($userId);
        abort_if($target === null, 404);

        if ($target->disabled) {
            return redirect("/admin/institutions/{$crmId}")->with('error', 'That staff account is inactive.');
        }

        if (empty($target->bceid_user_guid)) {
            return redirect("/admin/institutions/{$crmId}")->with('error', 'That staff account has no BCeID identity to sign in with.');
        }

        if (! $target->hasAnyRole([Role::INSTITUTION_ADMIN, Role::INSTITUTION_USER])) {
            return redirect("/admin/institutions/{$crmId}")->with('error', 'That staff account cannot access the institution portal.');
        }

        $admin = Auth::user();

        Auth::login($target);
        $request->session()->regenerate();

        $request->session()->put('impersonator_id', $admin->id);
        $request->session()->put('impersonator_name', $admin->name);
        $request->session()->put('portal_role', 'institution');
        $request->session()->put('portal_user', [
            'name' => $target->name,
            'email' => $target->email,
            'kind' => 'bceid',
        ]);
        $request->session()->put('portal_institution', $crmId);

        return redirect('/web');
    }

    /**
     * End an impersonation session started by loginAsStaff(): restore the
     * original ministry admin and return them to the admin portal.
     */
    public function stopImpersonating(Request $request): RedirectResponse
    {
        $adminId = $request->session()->get('impersonator_id');
        abort_if($adminId === null, 403);

        $admin = User::find($adminId);
        abort_if($admin === null, 403);

        Auth::login($admin);
        $request->session()->regenerate();

        $request->session()->forget(['impersonator_id', 'impersonator_name', 'portal_institution']);
        $request->session()->put('portal_role', 'ministry');
        $request->session()->put('portal_user', [
            'name' => $admin->name,
            'email' => $admin->email,
            'kind' => 'idir',
        ]);

        return redirect('/admin');
    }

    /**
     * Resolve a contact's BCeID username into its User GUID, Business GUID and
     * Business Legal Name via the BCeID web service, and persist them onto the
     * institution_users record. Mirrors the legacy Dynamics "Fetch BCeID Data".
     */
    public function fetchBceid(string $crmId, string $userId, BceidService $bceid): RedirectResponse
    {
        abort_if($this->institution($crmId) === null, 404);

        $contact = DB::table('institution_users')
            ->where('crm_id', $userId)
            ->where('institution_crm_id', $crmId)
            ->first();
        abort_if($contact === null, 404);

        $username = trim((string) ($contact->bceid_username ?: $contact->web_user_name ?? ''));
        if ($username === '') {
            return redirect("/admin/institutions/{$crmId}")
                ->with('error', 'This contact has no BCeID username to look up.');
        }

        try {
            $result = $bceid->search($username);
        } catch (RuntimeException $e) {
            return redirect("/admin/institutions/{$crmId}")
                ->with('error', "BCeID lookup failed: {$e->getMessage()}");
        }

        if (! $result['found']) {
            return redirect("/admin/institutions/{$crmId}")
                ->with('error', "No BCeID account found for username \"{$username}\".");
        }

        DB::table('institution_users')->where('crm_id', $userId)->update([
            'bceid_user_guid' => $result['user_guid'],
            'bceid_business_guid' => $result['business_guid'],
            'bceid_business_legal_name' => $result['business_legal_name'],
            'bceid_fetched_at' => now(),
        ]);

        return redirect("/admin/institutions/{$crmId}")
            ->with('success', "BCeID data fetched for \"{$username}\".");
    }

    // --- Institution BCeID administrator setup -------------------------

    private function bceidLookupKey(string $crmId): string
    {
        return "bceid_lookup.{$crmId}";
    }

    /** A BCeID administrator can only be set up while the institution has no applications, locations or staff. */
    private function hasNoApplicationsLocationsOrStaff(string $crmId): bool
    {
        $hasRecords = fn (string $table): bool => Schema::hasTable($table)
            && DB::table($table)->where('institution_crm_id', $crmId)->exists();

        return ! $hasRecords('applications') && ! $hasRecords('campuses') && ! $hasRecords('institution_users');
    }

    private function businessGuidInUseElsewhere(string $businessGuid, string $crmId): bool
    {
        return DB::table('institutions')
            ->whereRaw('lower(bceid_business_guid) = ?', [Str::lower($businessGuid)])
            ->where('crm_id', '!=', $crmId)
            ->exists();
    }

    private function userGuidInUseElsewhere(string $userGuid, string $crmId): bool
    {
        return DB::table('institution_users')
            ->whereRaw('lower(bceid_user_guid) = ?', [Str::lower($userGuid)])
            ->where('institution_crm_id', '!=', $crmId)
            ->exists();
    }

    /**
     * Fetch a BCeID account for a new institution's administrator. Nothing is saved
     * here; the result waits in the session until the admin confirms it.
     */
    public function lookupBceid(Request $request, string $crmId, BceidService $bceid): RedirectResponse
    {
        abort_if($this->institution($crmId) === null, 404);
        $back = "/admin/institutions/{$crmId}";

        if (! $this->hasNoApplicationsLocationsOrStaff($crmId)) {
            return redirect($back)->with('error', 'A BCeID administrator can only be set up for an institution with no applications, locations or staff.');
        }

        $data = $request->validate([
            'bceid_username' => ['required', 'string', 'max:60'],
        ]);
        $username = trim($data['bceid_username']);

        try {
            $result = $bceid->search($username);
        } catch (RuntimeException $e) {
            return redirect($back)->with('error', "BCeID lookup failed: {$e->getMessage()}");
        }

        if (! $result['found']) {
            return redirect($back)->with('error', "No BCeID account found for username \"{$username}\".");
        }

        if ($result['user_guid'] === null || $result['business_guid'] === null) {
            return redirect($back)->with('error', "BCeID account \"{$username}\" is not linked to a business, so it cannot administer an institution.");
        }

        if ($this->businessGuidInUseElsewhere($result['business_guid'], $crmId)) {
            return redirect($back)->with('error', 'This BCeID Business GUID is already assigned to another institution.');
        }

        if ($this->userGuidInUseElsewhere($result['user_guid'], $crmId)) {
            return redirect($back)->with('error', 'This BCeID user is already a contact at another institution.');
        }

        session()->put($this->bceidLookupKey($crmId), [
            'bceid_username' => $username,
            'bceid_user_guid' => $result['user_guid'],
            'bceid_business_guid' => $result['business_guid'],
            'bceid_business_legal_name' => $result['business_legal_name'],
            'first_name' => $result['first_name'],
            'last_name' => $result['last_name'],
            'email' => $result['email'],
        ]);

        return redirect($back)->with('success', "BCeID data fetched for \"{$username}\". Review the details below and confirm.");
    }

    /** Save the reviewed BCeID lookup: link the business GUID to the institution and add the administrator as a contact. */
    public function confirmBceidLookup(string $crmId): RedirectResponse
    {
        $institution = $this->institution($crmId);
        abort_if($institution === null, 404);
        $back = "/admin/institutions/{$crmId}";

        $lookup = session()->pull($this->bceidLookupKey($crmId));
        if ($lookup === null) {
            return redirect($back)->with('error', 'There is no BCeID lookup to confirm. Fetch the BCeID data again.');
        }

        if (! $this->hasNoApplicationsLocationsOrStaff($crmId)) {
            return redirect($back)->with('error', 'A BCeID administrator can only be set up for an institution with no applications, locations or staff.');
        }

        if ($this->businessGuidInUseElsewhere($lookup['bceid_business_guid'], $crmId)) {
            return redirect($back)->with('error', 'This BCeID Business GUID is already assigned to another institution.');
        }

        if ($this->userGuidInUseElsewhere($lookup['bceid_user_guid'], $crmId)) {
            return redirect($back)->with('error', 'This BCeID user is already a contact at another institution.');
        }

        $account = User::withTrashed()
            ->whereRaw('lower(bceid_user_guid) = ?', [Str::lower($lookup['bceid_user_guid'])])
            ->first();
        $email = trim((string) ($lookup['email'] ?? ''));

        if ($account === null) {
            if ($email === '') {
                return redirect($back)->with('error', 'This BCeID account has no email address, so a portal account cannot be created for it.');
            }

            if (User::withTrashed()->whereRaw('lower(email) = ?', [Str::lower($email)])->exists()) {
                return redirect($back)->with('error', "Another portal account already uses the email \"{$email}\".");
            }
        }

        $contact = [
            'crm_id' => (string) Str::uuid(),
            'institution_crm_id' => $crmId,
            'institution_name' => $institution->name,
            'first_name' => $lookup['first_name'],
            'last_name' => $lookup['last_name'],
            'full_name' => trim(($lookup['first_name'] ?? '').' '.($lookup['last_name'] ?? '')) ?: null,
            'email' => $lookup['email'],
            'bceid_username' => $lookup['bceid_username'],
            'bceid_user_guid' => $lookup['bceid_user_guid'],
            'bceid_business_guid' => $lookup['bceid_business_guid'],
            'bceid_business_legal_name' => $lookup['bceid_business_legal_name'],
            'bceid_fetched_at' => now(),
            'role' => 'Admin',
            'web_user_active' => true,
        ];

        DB::transaction(function () use ($crmId, $institution, $lookup, $contact, $account, $email): void {
            $institutionUpdate = ['bceid_business_guid' => Str::lower($lookup['bceid_business_guid'])];
            if (trim((string) $institution->institution_number) === '') {
                $institutionUpdate['institution_number'] = $this->nextInstitutionNumber();
            }
            DB::table('institutions')->where('crm_id', $crmId)->update($institutionUpdate);

            DB::table('institution_users')->insert($contact);

            $this->upsertBceidAdminAccount($account, $lookup, $email);
        });

        app(ProcessNotifier::class)->portalUserCreated($contact, $institution);

        return redirect($back)->with('success', "BCeID administrator \"{$lookup['bceid_username']}\" added to this institution.");
    }

    /**
     * Create (or reuse) the BCeID portal account for the confirmed administrator
     * and make it an active Institution Admin, so the staff grid can show its
     * role and the ministry "Login" action is available.
     *
     * @param  array<string, mixed>  $lookup
     */
    private function upsertBceidAdminAccount(?User $account, array $lookup, string $email): void
    {
        $name = trim(($lookup['first_name'] ?? '').' '.($lookup['last_name'] ?? '')) ?: $lookup['bceid_username'];

        if ($account === null) {
            $account = User::create([
                'guid' => Str::of((string) Str::orderedUuid())->replace('-', '')->toString(),
                'name' => $name,
                'first_name' => $lookup['first_name'] ?? null,
                'last_name' => $lookup['last_name'] ?? null,
                'email' => $email,
                'password' => Str::random(40),
                'bceid_username' => Str::upper((string) $lookup['bceid_username']),
                'bceid_user_guid' => $lookup['bceid_user_guid'],
                'bceid_business_guid' => $lookup['bceid_business_guid'],
            ]);
        } else {
            if ($account->trashed()) {
                $account->restore();
            }
            $account->update([
                'disabled' => false,
                'bceid_username' => $account->bceid_username ?: Str::upper((string) $lookup['bceid_username']),
                'bceid_business_guid' => $lookup['bceid_business_guid'],
            ]);
        }

        $account->roles()->detach(Role::whereIn('name', self::INSTITUTION_ROLES)->pluck('id'));
        $account->roles()->attach(Role::firstOrCreate(['name' => Role::INSTITUTION_ADMIN])->id);
    }

    /** A lower-case UUID not yet held by any institution or BCeID account. */
    private function uniqueBusinessGuid(): string
    {
        do {
            $guid = Str::lower((string) Str::uuid());
            $taken = DB::table('institutions')->whereRaw('lower(bceid_business_guid) = ?', [$guid])->exists()
                || DB::table('users')->whereRaw('lower(bceid_business_guid) = ?', [$guid])->exists()
                || (Schema::hasTable('institution_users')
                    && DB::table('institution_users')->whereRaw('lower(bceid_business_guid) = ?', [$guid])->exists());
        } while ($taken);

        return $guid;
    }

    /** Next institution number in the "ID-0000 000" sequence: the largest existing number plus one. */
    private function nextInstitutionNumber(): string
    {
        $max = DB::table('institutions')
            ->where('institution_number', 'like', 'ID-%')
            ->pluck('institution_number')
            ->map(fn ($n) => preg_match('/^ID-(\d{4}) ?(\d{3})$/', trim((string) $n), $m) ? (int) ($m[1].$m[2]) : 0)
            ->max() ?? 0;

        $digits = str_pad((string) ($max + 1), 7, '0', STR_PAD_LEFT);

        return 'ID-'.substr($digits, 0, 4).' '.substr($digits, 4);
    }

    public function cancelBceidLookup(string $crmId): RedirectResponse
    {
        abort_if($this->institution($crmId) === null, 404);

        session()->forget($this->bceidLookupKey($crmId));

        return redirect("/admin/institutions/{$crmId}");
    }

    // --- DBAs ----------------------------------------------------------

    private function dba(string $crmId, string $dbaId): ?object
    {
        return Schema::hasTable('dbas')
            ? DB::table('dbas')->where('crm_id', $dbaId)->where('institution_crm_id', $crmId)->first()
            : null;
    }

    public function createDba(string $crmId): Response
    {
        $institution = $this->institution($crmId);
        abort_if($institution === null, 404);

        return Inertia::render('Web/EditDba', [
            'institution' => $institution,
            'dba' => null,
            'submitUrl' => "/admin/institutions/{$crmId}/dbas",
            'method' => 'post',
            'cancelUrl' => "/admin/institutions/{$crmId}",
        ]);
    }

    public function editDba(string $crmId, string $dbaId): Response
    {
        $dba = $this->dba($crmId, $dbaId);
        abort_if($dba === null, 404);

        return Inertia::render('Web/EditDba', [
            'institution' => $this->institution($crmId),
            'dba' => $dba,
            'submitUrl' => "/admin/institutions/{$crmId}/dbas/{$dbaId}",
            'method' => 'put',
            'cancelUrl' => "/admin/institutions/{$crmId}",
        ]);
    }

    public function storeDba(Request $request, string $crmId): RedirectResponse
    {
        $institution = $this->institution($crmId);
        abort_if($institution === null, 404);

        $data = $this->validatedDba($request);

        DB::table('dbas')->insert(array_merge($data, [
            'crm_id' => (string) Str::uuid(),
            'institution_crm_id' => $crmId,
            'institution_name' => $institution->name,
        ]));

        app(ProcessNotifier::class)->dbaCreated($data, (string) $institution->name, $crmId, $this->adminActor());

        return redirect("/admin/institutions/{$crmId}")->with('success', 'DBA added.');
    }

    public function updateDba(Request $request, string $crmId, string $dbaId): RedirectResponse
    {
        $dba = $this->dba($crmId, $dbaId);
        abort_if($dba === null, 404);

        $data = $this->validatedDba($request);
        DB::table('dbas')->where('crm_id', $dbaId)->update($data);

        app(ProcessNotifier::class)->dbaChanged($dba, $data, $this->adminActor());

        return redirect("/admin/institutions/{$crmId}")->with('success', 'DBA updated.');
    }

    public function toggleDba(string $crmId, string $dbaId): RedirectResponse
    {
        $dba = $this->dba($crmId, $dbaId);
        abort_if($dba === null, 404);

        $status = $dba->status === 'Active' ? 'Inactive' : 'Active';
        DB::table('dbas')->where('crm_id', $dbaId)->update(['status' => $status]);

        return redirect("/admin/institutions/{$crmId}")->with('success', 'DBA '.($status === 'Active' ? 'reactivated' : 'deactivated').'.');
    }

    public function destroyDba(string $crmId, string $dbaId): RedirectResponse
    {
        $dba = $this->dba($crmId, $dbaId);
        abort_if($dba === null, 404);

        DB::table('dbas')->where('crm_id', $dbaId)->delete();

        return redirect("/admin/institutions/{$crmId}")->with('success', 'DBA removed.');
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
}
