<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Routes each audience to its portal via BC gov SSO (PDEX / Keycloak), mirroring
 * bcgov/nrsts: Ministry staff sign in with IDIR and land in the admin portal;
 * institutions sign in with BCeID and land in the web portal. PDEX authenticates
 * the user on its own origin, then POSTs the signed token back to /pdex-login.
 *
 * When PDEX is not configured (local dev) the /login page still renders the
 * IDIR/BCeID shortcut buttons, which create a working local user and sign in
 * through Laravel Auth so the guarded routes remain usable.
 */
class UserController extends Controller
{
    public function login(Request $request): Response|RedirectResponse
    {
        if ($loginUrl = config('services.pdex.login_url')) {
            return redirect()->away($loginUrl);
        }

        return Inertia::render('Auth/Login');
    }

    /**
     * PDEX callback: the authenticated SSO token is POSTed here. We decode the
     * token (base64 payload, no signature verification — PDEX is trusted), map
     * the user_type to a portal, then find-or-register the local user.
     */
    public function pdexLogin(Request $request): Response|RedirectResponse
    {
        $token = (string) $request->input('token');
        $userType = (string) $request->input('user_type');

        if ($token === '' || $userType === '') {
            abort(400, 'Missing SSO token.');
        }

        $payload = $this->decodeJwt($token);

        if ($payload === null || empty($payload['sub'])) {
            abort(401, 'Invalid SSO token.');
        }

        // PDEX posts the federated (Keycloak) logout URL; keep it so the navbar
        // can end the SSO session on logout.
        if ($logoutUrl = $request->input('logoutUrl')) {
            $request->session()->put('kc_logout_uri', $logoutUrl);
        }

        if (($audience = config('services.pdex.jwt_audience')) && ($payload['aud'] ?? null) !== $audience) {
            abort(401, 'SSO token audience mismatch.');
        }

        $portal = match ($userType) {
            'idir' => 'ministry',
            'bceid' => 'institution',
            // BCSC / student logins are not used by this portal.
            default => abort(403, 'Unsupported login type.'),
        };

        $user = $portal === 'ministry'
            ? User::where('idir_user_guid', 'ilike', $payload['idir_user_guid'] ?? $payload['sub'])->first()
            : User::where('bceid_user_guid', 'ilike', $payload['bceid_user_guid'] ?? $payload['sub'])->first();

        if ($user === null) {
            $user = $this->registerUser($payload, $portal);
        }

        $this->ensureGuestRole($user, $portal);

        if ($portal === 'institution') {
            $this->linkInstitution($user, $payload);
        }

        // Only users elevated beyond the GUEST role may enter a portal.
        $workingRoles = $portal === 'institution' ? Role::INSTITUTION_ROLES : Role::MINISTRY_ROLES;

        if ($user->disabled || ! $user->hasAnyRole($workingRoles)) {
            return Inertia::render('Auth/LoginPdex', [
                'hasAccess' => false,
                'pdexLoginUrl' => config('services.pdex.login_url'),
                'status' => 'Your account is not yet authorized. Please contact the ministry administrator.',
            ]);
        }

        return $this->signIn($request, $user, $portal);
    }

    /** Dev shortcut: sign in as Ministry staff with a working local user. */
    public function loginAsMinistry(Request $request): RedirectResponse
    {
        $user = User::firstOrCreate(
            ['email' => 'ministry.dev@gov.bc.ca'],
            [
                'guid' => Str::of((string) Str::orderedUuid())->replace('-', '')->toString(),
                'name' => 'Ministry Staff',
                'first_name' => 'Ministry',
                'last_name' => 'Staff',
                'idir_username' => 'MINISTRY.DEV',
                'idir_user_guid' => (string) Str::uuid(),
                'password' => Hash::make(Str::random(40)),
            ]
        );

        $this->attachRole($user, Role::MINISTRY_ADMIN);

        return $this->signIn($request, $user, 'ministry');
    }

    /** Dev shortcut: sign in as an institution with a working local user. */
    public function loginAsInstitution(Request $request): RedirectResponse
    {
        $user = User::firstOrCreate(
            ['email' => 'institution.dev@example.com'],
            [
                'guid' => Str::of((string) Str::orderedUuid())->replace('-', '')->toString(),
                'name' => 'Institution User',
                'first_name' => 'Institution',
                'last_name' => 'User',
                'bceid_username' => 'INSTITUTION.DEV',
                'bceid_user_guid' => (string) Str::uuid(),
                'password' => Hash::make(Str::random(40)),
            ]
        );

        $this->attachRole($user, Role::INSTITUTION_USER);

        return $this->signIn($request, $user, 'institution');
    }

    public function logout(Request $request): RedirectResponse
    {
        $ssoLogoutUrl = $request->session()->get('kc_logout_uri') ?: config('services.pdex.logout_url');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($ssoLogoutUrl) {
            return redirect()->away($ssoLogoutUrl);
        }

        return redirect('/login');
    }

    /**
     * Completes sign-in: logs the user in, mirrors the identity into the legacy
     * portal_* session keys the portals still read, and binds the institution.
     */
    private function signIn(Request $request, User $user, string $portal): RedirectResponse
    {
        Auth::login($user);
        $request->session()->regenerate();

        $request->session()->put('portal_role', $portal);
        $request->session()->put('portal_user', [
            'name' => $user->name,
            'email' => $user->email,
            'kind' => $portal === 'ministry' ? 'idir' : 'bceid',
        ]);

        if ($portal === 'institution') {
            $request->session()->put('portal_institution', $this->institutionIdFor($user));

            return redirect('/web');
        }

        $request->session()->forget('portal_institution');

        return redirect('/admin');
    }

    /** Resolve the institution a BCeID user belongs to (matched on guid). */
    private function institutionIdFor(User $user): ?string
    {
        if (! DB::getSchemaBuilder()->hasTable('institution_users')) {
            return null;
        }

        if ($user->bceid_user_guid) {
            $id = DB::table('institution_users')
                ->whereRaw('lower(bceid_user_guid) = ?', [Str::lower($user->bceid_user_guid)])
                ->whereNotNull('institution_crm_id')
                ->value('institution_crm_id');

            if ($id) {
                return $id;
            }
        }

        if ($user->email) {
            $id = DB::table('institution_users')
                ->whereRaw('lower(email) = ?', [Str::lower($user->email)])
                ->whereNotNull('institution_crm_id')
                ->value('institution_crm_id');

            if ($id) {
                return $id;
            }
        }

        // Dev fallback: bind to the institution with the most applications so the
        // portal has history to show.
        return DB::getSchemaBuilder()->hasTable('applications')
            ? DB::table('applications')
                ->whereNotNull('institution_crm_id')
                ->groupBy('institution_crm_id')
                ->orderByRaw('COUNT(*) DESC')
                ->value('institution_crm_id')
            : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function registerUser(array $payload, string $portal): User
    {
        $email = $payload['email'] ?? null;
        $name = $payload['name']
            ?? (trim(($payload['given_name'] ?? '').' '.($payload['family_name'] ?? ''))
                ?: ($payload['preferred_username'] ?? 'Unknown User'));

        $attributes = [
            'guid' => Str::of((string) Str::orderedUuid())->replace('-', '')->toString(),
            'name' => $name,
            'first_name' => $payload['given_name'] ?? null,
            'last_name' => $payload['family_name'] ?? null,
            'email' => $email,
            'password' => Hash::make(Str::lower((string) ($email ?? $payload['sub']))),
        ];

        if ($portal === 'ministry') {
            $attributes['idir_user_guid'] = $payload['idir_user_guid'] ?? $payload['sub'];
            $attributes['idir_username'] = Str::upper((string) ($payload['idir_username'] ?? $payload['preferred_username'] ?? ''));
        } else {
            $attributes['bceid_user_guid'] = $payload['bceid_user_guid'] ?? $payload['sub'];
            $attributes['bceid_business_guid'] = $payload['bceid_business_guid'] ?? null;
            $attributes['bceid_username'] = Str::upper((string) ($payload['bceid_username'] ?? $payload['preferred_username'] ?? ''));
        }

        return User::create($attributes);
    }

    /** Grant the audience GUEST role if the user has no roles yet. */
    private function ensureGuestRole(User $user, string $portal): void
    {
        if ($user->roles()->exists()) {
            return;
        }

        $this->attachRole($user, $portal === 'ministry' ? Role::MINISTRY_GUEST : Role::INSTITUTION_GUEST);
    }

    private function attachRole(User $user, string $roleName): void
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user->roles()->syncWithoutDetaching([$role->id]);
        $user->load('roles');
    }

    /**
     * Stamp the BCeID guids onto the matching institution contact so the portal
     * can scope to the institution on later logins.
     *
     * @param  array<string, mixed>  $payload
     */
    private function linkInstitution(User $user, array $payload): void
    {
        if (! $user->bceid_user_guid || ! DB::getSchemaBuilder()->hasTable('institution_users')) {
            return;
        }

        $query = DB::table('institution_users');

        if ($user->email) {
            $query->whereRaw('lower(email) = ?', [Str::lower($user->email)]);
        } else {
            $query->whereRaw('lower(bceid_user_guid) = ?', [Str::lower($user->bceid_user_guid)]);
        }

        $query->update([
            'bceid_user_guid' => $user->bceid_user_guid,
            'bceid_business_guid' => $user->bceid_business_guid,
        ]);
    }

    /**
     * Decode a JWT payload without verifying the signature (PDEX is trusted and
     * posts directly to us). Returns the claims array, or null when malformed.
     *
     * @return array<string, mixed>|null
     */
    private function decodeJwt(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) < 2) {
            return null;
        }

        $json = base64_decode(strtr($parts[1], '-_', '+/'), true);

        if ($json === false) {
            return null;
        }

        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : null;
    }
}
