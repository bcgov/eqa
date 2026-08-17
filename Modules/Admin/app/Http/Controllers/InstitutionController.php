<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Services\DesignationService;

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
                'ptibRequired' => DesignationService::ptibRequired($institution->qa_met_through),
                'ptibQaMetThrough' => DesignationService::PTIB_QA_MET_THROUGH,
            ],
            'applications' => Schema::hasTable('applications')
                ? DB::table('applications')->where('institution_crm_id', $crmId)->orderByDesc('application_date')->get()
                : collect(),
            'campuses' => Schema::hasTable('campuses')
                ? DB::table('campuses')->where('institution_crm_id', $crmId)->orderByDesc('primary_location')->orderBy('name')->get()
                : collect(),
            'users' => Schema::hasTable('institution_users')
                ? DB::table('institution_users')->where('institution_crm_id', $crmId)->orderBy('full_name')->get()
                : collect(),
            'dbas' => Schema::hasTable('dbas')
                ? DB::table('dbas')->where('institution_crm_id', $crmId)->orderBy('name')->get()
                : collect(),
        ]);
    }

    // --- Institution details --------------------------------------------

    public function edit(string $crmId): Response
    {
        $institution = $this->institution($crmId);
        abort_if($institution === null, 404);

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

        // Changing QA Met Through can invalidate an existing Designated status
        // (PTIB Standing only gates designation under the PTIB pathway).
        app(DesignationService::class)->reconcileAfterQaChange($crmId);

        return redirect("/admin/institutions/{$crmId}")->with('success', 'Institution details updated.');
    }

    /**
     * Ministry sets the institution's Designated status (EQA Status), EQA / PTIB
     * Standing and designation dates. Applies the coupled business rules and
     * cascades the standing onto the institution's open applications.
     */
    public function updateDesignation(Request $request, string $crmId): RedirectResponse
    {
        $institution = $this->institution($crmId);
        abort_if($institution === null, 404);

        // PTIB Standing is required and gates designation only when this
        // institution's QA is met through PTIB Designation.
        $ptibRequired = DesignationService::ptibRequired($institution->qa_met_through);

        $validator = Validator::make($request->all(), [
            'eqa_status' => ['required', Rule::in(DesignationService::EQA_STATUSES)],
            'eqa_standing' => ['nullable', Rule::in(DesignationService::STANDINGS)],
            'ptib_standing' => [$ptibRequired ? 'required' : 'nullable', Rule::in(DesignationService::STANDINGS)],
            'designation_start' => ['nullable', 'date'],
            'designation_expiry' => ['nullable', 'date'],
            'ptib_cert_expiry' => ['nullable', 'date'],
        ], [
            'ptib_standing.required' => 'PTIB Standing is required when QA is met through PTIB Designation.',
        ]);

        $validator->after(function ($validator) use ($request, $institution): void {
            if ($request->input('eqa_status') === 'Designated'
                && ! DesignationService::ptibPermitsDesignation($institution->qa_met_through, $request->input('ptib_standing'))) {
                $validator->errors()->add(
                    'eqa_status',
                    'EQA Status cannot be Designated because QA is met through PTIB Designation and PTIB Standing is not In Good Standing.'
                );
            }
        });

        $data = $validator->validate();

        app(DesignationService::class)->applyInstitutionDesignation($crmId, $data);

        return redirect("/admin/institutions/{$crmId}")->with('success', 'Designation status updated.');
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

        DB::table('dbas')->insert(array_merge($this->validatedDba($request), [
            'crm_id' => (string) Str::uuid(),
            'institution_crm_id' => $crmId,
            'institution_name' => $institution->name,
        ]));

        return redirect("/admin/institutions/{$crmId}")->with('success', 'DBA added.');
    }

    public function updateDba(Request $request, string $crmId, string $dbaId): RedirectResponse
    {
        $dba = $this->dba($crmId, $dbaId);
        abort_if($dba === null, 404);

        DB::table('dbas')->where('crm_id', $dbaId)->update($this->validatedDba($request));

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
