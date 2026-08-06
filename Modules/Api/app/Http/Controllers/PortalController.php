<?php

namespace Modules\Api\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    /**
     * Display a listing of portal resources.
     *
     * Source lineage: EQA API/Controllers/v1/PortalController.cs (aspnet controller, 57 actions)
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Api/Portal/Index', [
            'filters' => $request->only(['search', 'page', 'per_page']),
            'items' => [],
        ]);
    }

    /**
     * Show the form for creating a new portal resource.
     */
    public function create(): Response
    {
        return Inertia::render('Api/Portal/Create');
    }

    /**
     * Store a newly created portal resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        // TODO: persist $validated via the appropriate Api service/repository.

        return redirect()
            ->route('api.portal.index')
            ->with('success', 'Portal record created successfully.');
    }

    /**
     * Display the specified portal resource.
     */
    public function show(int $id): Response
    {
        return Inertia::render('Api/Portal/Show', [
            'id' => $id,
            'item' => null,
        ]);
    }

    /**
     * Show the form for editing the specified portal resource.
     */
    public function edit(int $id): Response
    {
        return Inertia::render('Api/Portal/Edit', [
            'id' => $id,
            'item' => null,
        ]);
    }

    /**
     * Update the specified portal resource in storage.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        // TODO: persist $validated for resource $id via the appropriate Api service/repository.

        return redirect()
            ->route('api.portal.index')
            ->with('success', 'Portal record updated successfully.');
    }

    /**
     * Remove the specified portal resource from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        // TODO: delete resource $id via the appropriate Api service/repository.

        return redirect()
            ->route('api.portal.index')
            ->with('success', 'Portal record deleted successfully.');
    }
}