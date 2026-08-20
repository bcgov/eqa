<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to users holding at least one of the given roles. Stacks on
 * top of the is.active portal guard for finer control (e.g. Ministry Admin only).
 */
class HasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->hasAnyRole($roles), 403);

        return $next($request);
    }
}
