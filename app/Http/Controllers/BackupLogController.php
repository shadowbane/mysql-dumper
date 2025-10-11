<?php

namespace App\Http\Controllers;

use App\Exceptions\BackupDestinationException;
use App\Models\BackupLog;
use App\Models\DataSource;
use App\Models\File;
use App\Services\BackupDestinationService;
use Exception;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupLogController extends Controller
{
    use AuthorizesRequests;

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

        // Filter by user permissions
        if (! auth()->user()->isAdministrator()) {
            // Non-administrators only see data sources they have access to
            $query->whereIn('backup_logs.data_source_id', auth()->user()->dataSources->pluck('id'));
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

        // Filter by locked status
        if ($request->filled('locked')) {
            $query->where('locked', $request->get('locked'));
        }

        // Sorting
        $sortBy = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        $query->orderBy($sortBy, $sortDirection);

        // Pagination
        $backupLogs = $query->customPaginate();

        // Add computed attributes
        $backupLogs->getCollection()->transform(function (BackupLog $backupLog) {
            $backupLog->human_size = $backupLog->getHumanSizeAttribute();
            $backupLog->human_duration = $backupLog->getHumanDuration();
            $backupLog->is_file_available = $backupLog->isFileAvailable();

            return $backupLog;
        });

        // Get data sources for filter
        $dataSources = DataSource::select('id', 'name')->orderBy('name')
            ->when(! auth()->user()->isAdministrator(), function (Builder $query) {
                $query->whereIn('id', auth()->user()->dataSources->pluck('id'));
            })
            ->get();

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
        $this->authorize('viewAny', $backupLog);

        $backupLog->load(['dataSource', 'timelines', 'files' => function ($query) {
            $query->whereNull('deleted_at');
        }]);

        // Add computed attributes
        $backupLog->human_size = $backupLog->getHumanSizeAttribute();
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
    public function download(Request $request, BackupLog $backupLog): StreamedResponse
    {
        $this->authorize('viewAny', $backupLog);

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
     * Download a specific backup file.
     *
     * @param  Request  $request
     * @param  BackupLog  $backupLog
     * @param  File  $file
     * @return StreamedResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function downloadFile(Request $request, BackupLog $backupLog, File $file): StreamedResponse|\Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('viewAny', $backupLog);

        // Verify the file belongs to this backup log
        if ($file->fileable_id !== $backupLog->id || $file->fileable_type !== BackupLog::class) {
            abort(403, 'File does not belong to this backup log.');
        }

        if ($file->deleted_at) {
            abort(404, 'File has been deleted.');
        }

        try {
            $destinationService = app(BackupDestinationService::class)
                ->getDestinationFromFile($file);

            $result = $destinationService->download($file);

            if (blank($result)) {
                throw BackupDestinationException::downloadDisabled();
            }

            if (is_string($result)) {
                return Inertia::location($result);
            }

            if ($result instanceof StreamedResponse) {
                return $result;
            }

            throw new \Exception('Unhandled response');
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
            $this->authorize('delete', $backupLog);

            if ($backupLog->locked) {
                abort(403, 'Backup log is locked.');
            }

            $lockName = "backup-file-delete-lock-{$file->id}";

            return $this->executeStoreWithLock($lockName, function () use ($file) {
                try {
                    DB::beginTransaction();

                    $destinationService = app(BackupDestinationService::class)
                        ->getDestinationFromFile($file);

                    // Delete file from storage
                    $destinationService->deleteFileRecord($file);

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

    public function lockBackup(Request $request, BackupLog $backupLog): RedirectResponse
    {
        try {
            $this->authorize('manageLock', $backupLog);

            $lockName = "backupLog-lockBackup-lock-{$backupLog->id}";
            $validator = Validator::make($request->all(), [
                'log_id' => ['required', 'ulid'],
                'locked' => ['required', 'bool'],
            ], [
                'log_id.required' => 'The log id field is required.',
                'locked.required' => 'The lock status field is required.',
                'locked.bool' => 'The lock status field must be a boolean.',
                'log_id.ulid' => 'The log id field must be a valid ULID.',
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $validated = $validator->validated();

            if ($validated['log_id'] !== $backupLog->id) {
                $validator->errors()->add('log_id', 'Invalid log ID');
                throw new ValidationException($validator);
            }

            return $this->executeStoreWithLock($lockName, function () use ($backupLog, $validated) {
                try {
                    DB::beginTransaction();

                    $backupLog->update([
                        'locked' => $validated['locked'],
                    ]);

                    DB::commit();

                    return redirect()->route('backup-logs.show', [
                        'backup_log' => $backupLog->id,
                    ])->with('success', $validated['locked'] ? 'Backup log locked successfully.' : 'Backup log unlocked successfully.');
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            });
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors());
        } catch (LockTimeoutException $e) {
            logger()->error("Failed acquire lock when locking Backup Log: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withErrors('Failed to acquire lock. Please try again later.');
        } catch (\Throwable $e) {
            logger()->error("Failed to lock Backup Log with ID {$backupLog->id}: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withErrors($e->getMessage());
        }
    }
}
