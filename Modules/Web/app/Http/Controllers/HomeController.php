<?php

namespace Modules\Web\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Display the home landing page.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Web/Home/Index');
    }

    /**
     * Display the about page.
     */
    public function about(Request $request): Response
    {
        return Inertia::render('Web/Home/About');
    }

    /**
     * Display the contact page.
     */
    public function contact(Request $request): Response
    {
        return Inertia::render('Web/Home/Contact');
    }

    /**
     * Handle submission of the contact form.
     */
    public function contactSubmit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ]);

        return redirect()
            ->back()
            ->with('success', 'Your message has been sent successfully.');
    }

    /**
     * Display the privacy policy page.
     */
    public function privacy(Request $request): Response
    {
        return Inertia::render('Web/Home/Privacy');
    }

    /**
     * Display the terms of service page.
     */
    public function terms(Request $request): Response
    {
        return Inertia::render('Web/Home/Terms');
    }

    /**
     * Display the frequently asked questions page.
     */
    public function faq(Request $request): Response
    {
        return Inertia::render('Web/Home/Faq');
    }

    /**
     * Display the help/support page.
     */
    public function help(Request $request): Response
    {
        return Inertia::render('Web/Home/Help');
    }

    /**
     * Display the login landing page.
     */
    public function login(Request $request): Response
    {
        return Inertia::render('Web/Home/Login');
    }

    /**
     * Display the error page.
     */
    public function error(Request $request): Response
    {
        return Inertia::render('Web/Home/Error', [
            'statusCode' => $request->integer('statusCode', 500),
        ]);
    }

    /**
     * Display the not found (404) page.
     */
    public function notFound(Request $request): Response
    {
        return Inertia::render('Web/Home/NotFound');
    }

    /**
     * Display the maintenance page.
     */
    public function maintenance(Request $request): Response
    {
        return Inertia::render('Web/Home/Maintenance');
    }

    /**
     * Display the sitemap page.
     */
    public function sitemap(Request $request): Response
    {
        return Inertia::render('Web/Home/Sitemap');
    }
}