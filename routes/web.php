<?php

use Inertia\Inertia;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Inertia::render('Index');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::any('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // Placeholder routes
    // Data Sources
    Route::get('data-sources', fn () => response()->json(['message' => 'Under Construction']))
        ->name('data-sources');
    // Schedules
    Route::get('schedules', fn () => response()->json(['message' => 'Under Construction']))
        ->name('schedules');
    // Backup Logs
    Route::get('logs', fn () => response()->json(['message' => 'Under Construction']))
        ->name('logs');
});
