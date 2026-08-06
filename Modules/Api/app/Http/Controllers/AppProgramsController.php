<?php

namespace Modules\Api\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Migrated from EQA API/Controllers/v1/AppProgramsController.cs (ASP.NET API controller).
 * Source IR contained no discovered actions; this controller provides the
 * standard resourceful skeleton to be filled in as endpoints are identified.
 */
class AppProgramsController extends Controller
{
    /**
     * Display a listing of app programs.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Api/AppPrograms/Index', [
            'filters' => $request->only(['search', 'page']),
        ]);
    }

    /**
     * Show the form for creating a new app program.
     */
    public function create(): Response
    {
        return Inertia::render('Api/AppPrograms/Create');
    }

    /**
     * Store a newly created app program.
     */
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            // TODO: define validation rules based on legacy AppProgramsController.cs
        ]);

        return Inertia::render('Api/AppPrograms/Index', [
            'validated' => $validated,
        ]);
    }

    /**
     * Display the specified app program.
     */
    public function show(int $id): Response
    {
        return Inertia::render('Api/AppPrograms/Show', [
            'id' => $id,
        ]);
    }

    /**
     * Show the form for editing the specified app program.
     */
    public function edit(int $id): Response
    {
        return Inertia::render('Api/AppPrograms/Edit', [
            'id' => $id,
        ]);
    }

    /**
     * Update the specified app program.
     */
    public function update(Request $request, int $id): Response
    {
        $validated = $request->validate([
            // TODO: define validation rules based on legacy AppProgramsController.cs
        ]);

        return Inertia::render('Api/AppPrograms/Show', [
            'id' => $id,
            'validated' => $validated,
        ]);
    }

    /**
     * Remove the specified app program.
     */
    public function destroy(int $id): Response
    {
        return Inertia::render('Api/AppPrograms/Index', [
            'deleted_id' => $id,
        ]);
    }
}