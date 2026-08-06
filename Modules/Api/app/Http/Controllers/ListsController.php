<?php

namespace Modules\Api\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Migrated from: EQA API/Controllers/v1/ListsController.cs (aspnet controller)
 *
 * The source controller exposed no discoverable actions in the intermediate
 * representation (action_count: 0). This controller is scaffolded with a
 * single index entry point so the module route/page wiring has a concrete
 * target; extend with additional methods as source actions are identified.
 */
class ListsController extends Controller
{
    /**
     * Display the Lists landing page.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Api/Lists/Index');
    }
}