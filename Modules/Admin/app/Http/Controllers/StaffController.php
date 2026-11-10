<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ministry staff maintenance, mirroring bcgov/nrsts: list all IDIR (Ministry)
 * users and let an administrator switch each one's role (Admin / User / Guest)
 * and active status. Only Ministry/Super admins may make changes.
 */
class StaffController extends Controller
{
    private const MINISTRY_ROLES = [Role::MINISTRY_ADMIN, Role::MINISTRY_USER, Role::MINISTRY_GUEST];

    public function index(): Response
    {
        $staff = User::with('roles')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', self::MINISTRY_ROLES))
            ->orderBy('name')
            ->get()
            ->map(function (User $user) {
                $names = $user->roles->pluck('name');
                $user->access_type = $names->contains(Role::MINISTRY_ADMIN) ? 'Admin'
                    : ($names->contains(Role::MINISTRY_USER) ? 'User' : 'Guest');

                return $user;
            });

        return Inertia::render('Admin/Staff', [
            'staff' => $staff,
            'canManage' => $this->canManage(),
        ]);
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        abort_unless($this->canManage(), 403);
        $data = $request->validate(['disabled' => ['required', 'boolean']]);

        $user->update(['disabled' => $data['disabled']]);

        return redirect()->route('admin.staff')->with('success', 'Staff status updated.');
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        abort_unless($this->canManage(), 403);
        $data = $request->validate(['role' => ['required', 'in:Admin,User,Guest']]);

        $newRole = match ($data['role']) {
            'Admin' => Role::MINISTRY_ADMIN,
            'User' => Role::MINISTRY_USER,
            default => Role::MINISTRY_GUEST,
        };

        $role = Role::firstOrCreate(['name' => $newRole]);
        $user->roles()->detach(Role::whereIn('name', self::MINISTRY_ROLES)->pluck('id'));
        $user->roles()->attach($role->id);

        return redirect()->route('admin.staff')->with('success', 'Staff role updated.');
    }

    private function canManage(): bool
    {
        $user = Auth::user();

        return $user !== null && $user->hasAnyRole([Role::SUPER_ADMIN, Role::MINISTRY_ADMIN]);
    }
}
