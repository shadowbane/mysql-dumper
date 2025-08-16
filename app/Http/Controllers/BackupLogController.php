<?php

namespace App\Http\Controllers;

use App\Models\BackupLog;
use App\Models\DataSource;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupLogController extends Controller
{
    /**
     * Display a listing of backup logs.
     *
     * @param  Request  $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $query = BackupLog::with(['dataSource', 'timelines'])
            ->select('backup_logs.*');

        // Search
        if ($request->filled('search')) {
            $searchTerm = $request->get('search');
            $query->whereHas('dataSource', function (Builder $q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('database', 'like', "%{$searchTerm}%");
            });
        }

        // Filter by data source
        if ($request->filled('data_source_id')) {
            $query->where('data_source_id', $request->get('data_source_id'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        // Pagination
        $backupLogs = $query->customPaginate();

        // Add computed attributes
        $backupLogs->getCollection()->transform(function (BackupLog $backupLog) {
            // $backupLog->human_size = $backupLog->human_size;
            $backupLog->human_duration = $backupLog->getHumanDuration();
            $backupLog->is_file_available = $backupLog->isFileAvailable();

            return $backupLog;
        });

        // Get data sources for filter
        $dataSources = DataSource::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('BackupLogs/Index', [
            'backupLogs' => $backupLogs,
            'dataSources' => $dataSources,
        ]);
    }

    /**
     * Display the specified backup log.
     *
     * @param  Request  $request
     * @param  BackupLog  $backupLog
     * @return Response
     */
    public function show(Request $request, BackupLog $backupLog): Response
    {
        $backupLog->load(['dataSource', 'timelines']);

        // Add computed attributes
        $backupLog->human_size = $backupLog->human_size;
        $backupLog->human_duration = $backupLog->getHumanDuration();
        $backupLog->is_file_available = $backupLog->isFileAvailable();

        // Add duration calculations to timelines
        $backupLog->timelines->transform(function ($timeline) {
            $timeline->duration_from_previous = $timeline->getDurationFromPrevious();
            $timeline->human_duration_from_previous = $timeline->getHumanDurationFromPrevious();

            return $timeline;
        });

        return Inertia::render('BackupLogs/Show', [
            'backupLog' => $backupLog,
        ]);
    }

    /**
     * Download the backup file.
     *
     * @param  Request  $request
     * @param  BackupLog  $backupLog
     * @return StreamedResponse
     */
    public function download(Request $request, BackupLog $backupLog): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (! $backupLog->isFileAvailable()) {
            abort(404, 'Backup file not found or has been deleted.');
        }

        try {
            $disk = Storage::disk($backupLog->disk);

            if (! $disk->exists($backupLog->file_path)) {
                abort(404, 'Backup file not found on storage.');
            }

            return $disk->download($backupLog->file_path, $backupLog->filename);

        } catch (Exception $e) {
            abort(500, 'Error downloading backup file: '.$e->getMessage());
        }
    }

    /**
     * Delete the backup file (but keep the log).
     *
     * @param  Request  $request
     * @param  BackupLog  $backupLog
     * @return JsonResponse
     */
    public function deleteFile(Request $request, BackupLog $backupLog): JsonResponse
    {
        try {
            if (! $backupLog->isFileAvailable()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup file not found or already deleted.',
                ], 404);
            }

            // Delete file from storage
            $disk = Storage::disk($backupLog->disk);
            if ($disk->exists($backupLog->file_path)) {
                $disk->delete($backupLog->file_path);
            }

            // Mark file as deleted in database
            $backupLog->markFileAsDeleted();

            return response()->json([
                'success' => true,
                'message' => 'Backup file deleted successfully. Log entry preserved.',
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete backup file: '.$e->getMessage(),
            ], 500);
        }
    }
}
