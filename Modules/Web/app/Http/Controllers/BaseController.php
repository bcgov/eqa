<?php

namespace Modules\Web\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Base controller providing shared helper behaviour for Web module controllers.
 *
 * Migrated from EQA WEB/Helpers/BaseController.cs (ASP.NET base controller helpers).
 */
abstract class BaseController extends Controller
{
    /**
     * Render a standard Inertia response for a given component with props.
     */
    protected function renderView(string $component, array $props = []): Response
    {
        return Inertia::render($component, $props);
    }

    /**
     * Render an Inertia response carrying a flash success message.
     */
    protected function renderWithSuccess(string $component, string $message, array $props = []): Response
    {
        return Inertia::render($component, array_merge($props, [
            'flash' => [
                'success' => $message,
            ],
        ]));
    }

    /**
     * Render an Inertia response carrying a flash error message.
     */
    protected function renderWithError(string $component, string $message, array $props = []): Response
    {
        return Inertia::render($component, array_merge($props, [
            'flash' => [
                'error' => $message,
            ],
        ]));
    }

    /**
     * Redirect back to the previous location with a flash success message.
     */
    protected function redirectWithSuccess(string $route, string $message, array $parameters = []): RedirectResponse
    {
        return redirect()->route($route, $parameters)->with('success', $message);
    }

    /**
     * Redirect back to the previous location with a flash error message.
     */
    protected function redirectWithError(string $route, string $message, array $parameters = []): RedirectResponse
    {
        return redirect()->route($route, $parameters)->with('error', $message);
    }

    /**
     * Resolve the currently authenticated user, or null if unauthenticated.
     */
    protected function currentUser(Request $request): ?object
    {
        return $request->user();
    }
}