<?php

namespace Modules\Api\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Migrated from EQA API/Controllers/v1/Portal.SettingsController.cs
 * Source connector: aspnet
 */
class SettingsController extends Controller
{
    /**
     * Display the settings resource.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Api/Settings/Index', [
            'settings' => [],
        ]);
    }

    /**
     * Update the settings resource.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        // TODO: persist settings via the appropriate service/repository.

        return redirect()->back()->with('success', 'Settings updated successfully.');
    }
}