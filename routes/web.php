<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->guest()) {
        return redirect()->route('login');
    }

    return redirect()->route('dashboard');
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

    // Data Sources
    Route::prefix('data-sources')->name('data-sources.')->group(function () {
        Route::resource('/', \App\Http\Controllers\DataSourceController::class)
            ->parameters([
                '' => 'data_source',
            ]);

        // Trigger backup for single source
        Route::post('{data_source}/backup', [\App\Http\Controllers\DataSourceController::class, 'backupSingleDB'])
            ->name('single-backup');

        // Test connection after data is saved
        Route::post('{data_source}/test-connection', [\App\Http\Controllers\DataSourceController::class, 'testConnection'])
            ->name('test');

        // Test connection before data is saved
        Route::post('test-connection', [\App\Http\Controllers\DataSourceController::class, 'testConnectionData'])
            ->name('test-connection');
    });

    // Schedules
    Route::prefix('schedules')->name('schedules.')->group(function () {
        Route::resource('/', \App\Http\Controllers\ScheduleController::class)
            ->parameters([
                '' => 'schedule',
            ]);
    });

    // Backup Logs
    Route::prefix('backup-logs')->name('backup-logs.')->group(function () {
        Route::get('/', [\App\Http\Controllers\BackupLogController::class, 'index'])
            ->name('index');
        Route::get('{backup_log}', [\App\Http\Controllers\BackupLogController::class, 'show'])
            ->name('show');
        Route::delete('{backup_log}/file', [\App\Http\Controllers\BackupLogController::class, 'deleteFile'])
            ->name('delete-file');
        Route::get('{backup_log}/download', [\App\Http\Controllers\BackupLogController::class, 'download'])
            ->name('download');
    });
});
