<?php

namespace Modules\Api\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class IntakeFormController extends Controller
{
    /**
     * Display a listing of the intake forms.
     */
    public function index(Request $request): Response
    {
        $intakeForms = [];

        return Inertia::render('Api/IntakeForm/Index', [
            'intakeForms' => $intakeForms,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Show the form for creating a new intake form.
     */
    public function create(): Response
    {
        return Inertia::render('Api/IntakeForm/Create');
    }

    /**
     * Store a newly created intake form in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'data' => ['nullable', 'array'],
        ]);

        return redirect()
            ->route('api.intake-form.index')
            ->with('success', 'Intake form created successfully.');
    }

    /**
     * Display the specified intake form.
     */
    public function show(int $id): Response
    {
        $intakeForm = [
            'id' => $id,
        ];

        return Inertia::render('Api/IntakeForm/Show', [
            'intakeForm' => $intakeForm,
        ]);
    }

    /**
     * Show the form for editing the specified intake form.
     */
    public function edit(int $id): Response
    {
        $intakeForm = [
            'id' => $id,
        ];

        return Inertia::render('Api/IntakeForm/Edit', [
            'intakeForm' => $intakeForm,
        ]);
    }

    /**
     * Update the specified intake form in storage.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'data' => ['nullable', 'array'],
        ]);

        return redirect()
            ->route('api.intake-form.show', $id)
            ->with('success', 'Intake form updated successfully.');
    }

    /**
     * Remove the specified intake form from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        return redirect()
            ->route('api.intake-form.index')
            ->with('success', 'Intake form deleted successfully.');
    }
}