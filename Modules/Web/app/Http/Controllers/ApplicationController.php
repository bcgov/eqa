<?php

namespace Modules\Web\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Web\Models\Application;

class ApplicationController extends Controller
{
    /**
     * Display a listing of applications.
     */
    public function index(Request $request): Response
    {
        $applications = Application::query()
            ->when($request->input('search'), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Web/Application/Index', [
            'applications' => $applications,
            'filters' => $request->only('search'),
        ]);
    }

    /**
     * Show the form for creating a new application.
     */
    public function create(): Response
    {
        return Inertia::render('Web/Application/Create');
    }

    /**
     * Store a newly created application in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $application = Application::create($validated);

        return redirect()
            ->route('applications.show', $application)
            ->with('success', 'Application created successfully.');
    }

    /**
     * Display the specified application.
     */
    public function show(Application $application): Response
    {
        return Inertia::render('Web/Application/Show', [
            'application' => $application,
        ]);
    }

    /**
     * Show the form for editing the specified application.
     */
    public function edit(Application $application): Response
    {
        return Inertia::render('Web/Application/Edit', [
            'application' => $application,
        ]);
    }

    /**
     * Update the specified application in storage.
     */
    public function update(Request $request, Application $application): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $application->update($validated);

        return redirect()
            ->route('applications.show', $application)
            ->with('success', 'Application updated successfully.');
    }

    /**
     * Remove the specified application from storage.
     */
    public function destroy(Application $application): RedirectResponse
    {
        $application->delete();

        return redirect()
            ->route('applications.index')
            ->with('success', 'Application deleted successfully.');
    }

    /**
     * Approve the specified application.
     */
    public function approve(Application $application): RedirectResponse
    {
        $application->update(['status' => 'approved']);

        return redirect()
            ->route('applications.show', $application)
            ->with('success', 'Application approved successfully.');
    }

    /**
     * Reject the specified application.
     */
    public function reject(Application $application): RedirectResponse
    {
        $application->update(['status' => 'rejected']);

        return redirect()
            ->route('applications.show', $application)
            ->with('success', 'Application rejected successfully.');
    }

    /**
     * Restore the specified application to a pending state.
     */
    public function restore(Application $application): RedirectResponse
    {
        $application->update(['status' => 'pending']);

        return redirect()
            ->route('applications.show', $application)
            ->with('success', 'Application restored successfully.');
    }
}