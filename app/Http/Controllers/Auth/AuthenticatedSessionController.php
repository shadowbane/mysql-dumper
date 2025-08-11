<?php

namespace App\Http\Controllers\Auth;

use App\DTO\LoginDTO;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginDTO $request): \Illuminate\Http\RedirectResponse
    {
        $request->authenticate();

        session()->regenerate();

        return redirect()->intended(route('dashboard', [], false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): \Illuminate\Http\RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
