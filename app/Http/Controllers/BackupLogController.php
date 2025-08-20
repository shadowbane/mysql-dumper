<?php

namespace App\Http\Controllers;

use App\Models\BackupLog;
use App\Models\DataSource;
use App\Models\File;
use Exception;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $query = BackupLog::with(['dataSource', 'timelines', 'files'])
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
        $sortBy = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

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
        $backupLog->load(['dataSource', 'timelines', 'files' => function ($query) {
            $query->whereNull('deleted_at');
        }]);

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
     * @return RedirectResponse
     */
    public function deleteFile(Request $request, BackupLog $backupLog): RedirectResponse
    {
        try {
            $lockName = "backup-log-delete-file-lock-{$backupLog->id}";

            return $this->executeStoreWithLock($lockName, function () use ($backupLog) {
                try {
                    DB::beginTransaction();

                    if (! $backupLog->isFileAvailable()) {
                        DB::rollBack();

                        return back()->withErrors(['message' => 'Backup file not found or already deleted.']);
                    }

                    // Delete file from storage
                    $disk = Storage::disk($backupLog->disk);
                    if ($disk->exists($backupLog->file_path)) {
                        $disk->delete($backupLog->file_path);
                    }

                    // Mark file as deleted in database
                    $backupLog->markFileAsDeleted();

                    DB::commit();

                    return back()->with('success', 'Backup file deleted successfully. Log entry preserved.');

                } catch (Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            });
        } catch (LockTimeoutException $e) {
            logger()->error("Failed acquire lock when deleting backup file: {$e->getMessage()}", [
                'exception' => $e,
                'backup_log_id' => $backupLog->id,
            ]);

            return back()->withErrors(['message' => 'Failed to acquire lock. Please try again later.']);
        } catch (\Throwable $e) {
            logger()->error("Failed to delete backup file for log ID {$backupLog->id}: {$e->getMessage()}", [
                'exception' => $e,
                'backup_log_id' => $backupLog->id,
            ]);

            return back()->withErrors(['message' => 'Failed to delete backup file: '.$e->getMessage()]);
        }
    }

    /**
     * Download a specific backup file.
     *
     * @param  Request  $request
     * @param  BackupLog  $backupLog
     * @param  File  $file
     * @return StreamedResponse
     */
    public function downloadFile(Request $request, BackupLog $backupLog, File $file): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        // Verify the file belongs to this backup log
        if ($file->fileable_id !== $backupLog->id || $file->fileable_type !== BackupLog::class) {
            abort(403, 'File does not belong to this backup log.');
        }

        if ($file->deleted_at) {
            abort(404, 'File has been deleted.');
        }

        try {
            $disk = Storage::disk($file->disk);

            if (! $disk->exists($file->path)) {
                abort(404, 'File not found on storage.');
            }

            return $disk->download($file->path, $file->filename);

        } catch (Exception $e) {
            abort(500, 'Error downloading file: '.$e->getMessage());
        }
    }

    /**
     * Delete a specific backup file.
     *
     * @param  Request  $request
     * @param  BackupLog  $backupLog
     * @param  File  $file
     * @return RedirectResponse
     */
    public function deleteIndividualFile(Request $request, BackupLog $backupLog, File $file): RedirectResponse
    {
        // Verify the file belongs to this backup log
        if ($file->fileable_id !== $backupLog->id || $file->fileable_type !== BackupLog::class) {
            abort(403, 'File does not belong to this backup log.');
        }

        if ($file->deleted_at) {
            return back()->withErrors(['message' => 'File is already deleted.']);
        }

        try {
            $lockName = "backup-file-delete-lock-{$file->id}";

            return $this->executeStoreWithLock($lockName, function () use ($file) {
                try {
                    DB::beginTransaction();

                    // Delete file from storage
                    $disk = Storage::disk($file->disk);
                    if ($disk->exists($file->path)) {
                        $disk->delete($file->path);
                    }

                    // Soft delete the file record
                    $file->delete();

                    DB::commit();

                    return back()->with('success', 'Backup file deleted successfully.');

                } catch (Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            });
        } catch (LockTimeoutException $e) {
            logger()->error("Failed acquire lock when deleting individual file: {$e->getMessage()}", [
                'exception' => $e,
                'file_id' => $file->id,
            ]);

            return back()->withErrors(['message' => 'Failed to acquire lock. Please try again later.']);
        } catch (\Throwable $e) {
            logger()->error("Failed to delete individual file ID {$file->id}: {$e->getMessage()}", [
                'exception' => $e,
                'file_id' => $file->id,
            ]);

            return back()->withErrors(['message' => 'Failed to delete file: '.$e->getMessage()]);
        }
    }
}
