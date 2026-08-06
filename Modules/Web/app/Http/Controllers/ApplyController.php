<?php

namespace Modules\Web\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApplyController extends Controller
{
    /**
     * Display the apply form/index view.
     *
     * Source: EQA WEB/Controllers/ApplyController.cs
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Web/Apply/Index', [
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Handle submission of the apply form.
     *
     * Source: EQA WEB/Controllers/ApplyController.cs
     */
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        return Inertia::render('Web/Apply/Confirmation', [
            'data' => $validated,
        ]);
    }
}