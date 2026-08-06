<?php

namespace Modules\Api\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Api/Application/Index', [
            'filters' => $request->only(['search', 'page']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Api/Application/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        return Inertia::render('Api/Application/Index', [
            'data' => $validated,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): Response
    {
        return Inertia::render('Api/Application/Show', [
            'id' => $id,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): Response
    {
        return Inertia::render('Api/Application/Edit', [
            'id' => $id,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): Response
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        return Inertia::render('Api/Application/Show', [
            'id' => $id,
            'data' => $validated,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): Response
    {
        return Inertia::render('Api/Application/Index', [
            'deleted_id' => $id,
        ]);
    }
}