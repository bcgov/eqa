<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Entry point that routes each audience to its portal, mirroring the
 * bcgov/usp pattern: Ministry staff authenticate with IDIR and land in the
 * admin portal; institutions authenticate with BCeID and land in the web
 * portal. The real BC gov SSO is stubbed here (a session role) and swapped
 * in later.
 */
class UserController extends Controller
{
    public function login(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function loginAsMinistry(Request $request): RedirectResponse
    {
        // TODO: replace with BC gov SSO (IDIR) — see bcgov/usp UserController.
        $request->session()->put('portal_role', 'ministry');
        $request->session()->put('portal_user', ['name' => 'Ministry Staff', 'kind' => 'idir']);

        return redirect('/admin');
    }

    public function loginAsInstitution(Request $request): RedirectResponse
    {
        // TODO: replace with BC gov SSO (BCeID). For now, bind the session to a
        // real institution that has applications so the portal shows history.
        $institutionId = DB::getSchemaBuilder()->hasTable('applications')
            ? DB::table('applications')
                ->whereNotNull('institution_crm_id')
                ->groupBy('institution_crm_id')
                ->orderByRaw('COUNT(*) DESC')
                ->value('institution_crm_id')
            : null;

        $request->session()->put('portal_role', 'institution');
        $request->session()->put('portal_institution', $institutionId);
        $request->session()->put('portal_user', ['name' => 'Institution User', 'kind' => 'bceid']);

        return redirect('/web');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['portal_role', 'portal_user']);

        return redirect('/login');
    }
}
