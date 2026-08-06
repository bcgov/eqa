<?php

namespace Modules\Web\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Web\Models\Institution;

class InstitutionController extends Controller
{
    /**
     * Display a listing of institutions.
     */
    public function index(Request $request): Response
    {
        $institutions = Institution::query()
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Institution/Index', [
            'institutions' => $institutions,
            'filters' => $request->only('search'),
        ]);
    }

    /**
     * Display the specified institution.
     */
    public function show(int $id): Response
    {
        $institution = Institution::query()->findOrFail($id);

        return Inertia::render('Institution/Show', [
            'institution' => $institution,
        ]);
    }
}