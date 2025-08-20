<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BackupLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataSourceController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

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
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::any('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // Data Sources
    Route::prefix('data-sources')->name('data-sources.')->group(function () {
        Route::resource('/', DataSourceController::class)
            ->parameters([
                '' => 'data_source',
            ]);

        // Trigger backup for single source
        Route::post('{data_source}/backup', [DataSourceController::class, 'backupSingleDB'])
            ->name('single-backup');

        // Test connection after data is saved
        Route::post('{data_source}/test-connection', [DataSourceController::class, 'testConnection'])
            ->name('test');

        // Test connection before data is saved
        Route::post('test-connection', [DataSourceController::class, 'testConnectionData'])
            ->name('test-connection');
    });

    // Schedules
    Route::resource('schedules', ScheduleController::class);

    // Backup Logs
    Route::prefix('backup-logs')->name('backup-logs.')->group(function () {
        Route::get('/', [BackupLogController::class, 'index'])
            ->name('index');
        Route::get('{backup_log}', [BackupLogController::class, 'show'])
            ->name('show');
        Route::delete('{backup_log}/file', [BackupLogController::class, 'deleteFile'])
            ->name('delete-file');
        Route::get('{backup_log}/download', [BackupLogController::class, 'download'])
            ->name('download');
    });
});
