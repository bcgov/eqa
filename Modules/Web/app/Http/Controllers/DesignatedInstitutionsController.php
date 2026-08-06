<?php

namespace Modules\Web\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DesignatedInstitutionsController extends Controller
{
    /**
     * Display the designated institutions index page.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Web/DesignatedInstitutions/Index');
    }
}