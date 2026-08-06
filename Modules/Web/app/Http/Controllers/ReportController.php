<?php

namespace Modules\Web\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Display the report index page.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Web/Report/Index');
    }
}