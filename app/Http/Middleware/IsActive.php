<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards a portal: the user must be authenticated, not disabled, carry the
 * expected SSO guid, and hold a working role for the requested audience.
 * Otherwise they are bounced to the PDEX login. Mirrors bcgov/nrsts IsActive.
 */
class IsActive
{
    /**
     * @param  'ministry'|'institution'  $audience
     */
    public function handle(Request $request, Closure $next, string $audience = 'ministry'): Response
    {
        $loginUrl = config('services.pdex.login_url') ?: route('login');

        $user = Auth::user();

        if ($user === null) {
            return redirect()->guest($loginUrl);
        }

        if ($user->disabled) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->away($loginUrl);
        }

        $requiredGuid = $audience === 'institution' ? $user->bceid_user_guid : $user->idir_user_guid;
        $allowedRoles = $audience === 'institution' ? Role::INSTITUTION_ROLES : Role::MINISTRY_ROLES;

        if (empty($requiredGuid) || ! $user->hasAnyRole($allowedRoles)) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
