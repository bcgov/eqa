<?php

declare(strict_types=1);

namespace Modules\Web\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Services\ProcessNotifier;

/**
 * The institution (BCeID) web portal: an institution views its own profile
 * and application history, opens an application, and submits a new one. The
 * logged-in institution is bound at login (session portal_institution); real
 * BCeID SSO would map the authenticated user to their institution.
 */
class WebPortalController extends Controller
{
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

    /** Workflow stages that mean the institution already has a submitted / in-review application. */
    private const ACTIVE_STAGES = ['pending_review', 'fees_payment', 'under_review', 'suitability_review'];

    private function hasActiveApplication(object $institution): bool
    {
        return DB::getSchemaBuilder()->hasTable('applications')
            && DB::table('applications')
                ->where('institution_crm_id', $institution->crm_id)
                ->whereIn('workflow_stage', self::ACTIVE_STAGES)
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
        ]);
    }

    public function show(Request $request, string $crmId): Response
    {
        $application = DB::table('applications')->where('crm_id', $crmId)->first();
        abort_if($application === null, 404);

        return Inertia::render('Web/ViewApplication', [
            'institution' => $this->institution($request),
            'application' => $application,
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
            'enrolmentTypes' => ['FTE', 'Enrolment'],
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
            'intl_students_other' => ['nullable', 'integer', 'min:0'],
            'in_person_students' => ['nullable', 'integer', 'min:0'],
            'online_students' => ['nullable', 'integer', 'min:0'],
            'enrolment_type' => ['nullable', 'string', 'max:50'],
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

        return Inertia::render('Web/NewApplication', ['institution' => $institution]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institution = $this->institution($request);
        abort_if($institution === null, 403);

        if ($this->hasActiveApplication($institution)) {
            return redirect('/web/applications')
                ->with('error', 'You already have an application that is submitted or under review. You cannot submit a new one until it is finalized.');
        }

        $data = $request->validate([
            'total_enrolment' => ['nullable', 'integer', 'min:0'],
            'intl_students_permit' => ['nullable', 'integer', 'min:0'],
            'intl_students_other' => ['nullable', 'integer', 'min:0'],
            'in_person_students' => ['nullable', 'integer', 'min:0'],
            'enrolment_type' => ['nullable', 'string', 'max:50'],
        ]);

        $maxNum = (int) DB::table('applications')
            ->selectRaw("MAX(NULLIF(regexp_replace(reference, '[^0-9]', '', 'g'), '')::bigint) AS m")
            ->value('m');
        $reference = 'APP-'.str_pad((string) ($maxNum + 1), 8, '0', STR_PAD_LEFT);

        $isReapplication = app(ProcessNotifier::class)->isReapplication($institution);

        DB::table('applications')->insert(array_merge($data, [
            'crm_id' => (string) Str::uuid(),
            'reference' => $reference,
            'status' => 'Pending Review',
            'workflow_stage' => 'pending_review',
            'institution_crm_id' => $institution->crm_id,
            'institution_name' => $institution->name,
            'owner_name' => $institution->business_owner ?? null,
            'application_date' => Carbon::now()->toDateString(),
            'total_due' => 0,
        ]));

        app(ProcessNotifier::class)->applicationSubmitted($institution, $reference, $isReapplication);

        return redirect('/web/applications')->with('success', 'Application '.$reference.' submitted for review.');
    }

    /** Human label for the acting institution portal user, for change notifications. */
    private function portalActor(?object $institution): string
    {
        $name = trim((string) ($institution->name ?? ''));

        return $name !== '' ? $name.' (institution portal)' : 'Institution portal';
    }
}
