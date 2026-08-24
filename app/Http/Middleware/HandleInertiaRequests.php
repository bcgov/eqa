<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = Auth::user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user
                    ? [
                        'name' => $user->name,
                        'email' => $user->email,
                        'kind' => $request->session()->get('portal_role') === 'ministry' ? 'idir' : 'bceid',
                        'roles' => $user->roles->pluck('name'),
                    ]
                    : $request->session()->get('portal_user'),
                'role' => $request->session()->get('portal_role'),
            ],
            'impersonating' => $request->session()->has('impersonator_id')
                ? ['name' => $request->session()->get('impersonator_name')]
                : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            // Federated (Keycloak) logout URL captured at PDEX login.
            'logoutUrl' => $request->session()->get('kc_logout_uri'),
        ];
    }
}
