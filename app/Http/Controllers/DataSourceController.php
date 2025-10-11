<?php

namespace App\Http\Controllers;

use App\Contracts\BackupServiceInterface;
use App\DTO\ConnectionDTO;
use App\Enums\BackupStatusEnum;
use App\Events\BackupRequested;
use App\Http\Requests\StoreDataSourceRequest;
use App\Http\Requests\UpdateDataSourceRequest;
use App\Models\DataSource;
use Exception;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DataSourceController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly BackupServiceInterface $backupService
    ) {}

    /**
     * @param  Request  $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $query = DataSource::with('latestBackupLog');

        // Filter by user permissions
        if (! auth()->user()->isAdministrator()) {
            // Non-administrators only see data sources they have access to
            $query->whereHas('users', function (Builder $q) use ($request) {
                $q->where('users.id', $request->user()->id);
            });
        }

        // Search
        if ($request->filled('search')) {
            $searchTerm = $request->get('search');
            $query->where(function (Builder $q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('host', 'like', "%{$searchTerm}%")
                    ->orWhere('database', 'like', "%{$searchTerm}%")
                    ->orWhere('username', 'like', "%{$searchTerm}%");
            });
        }

        // Filter by is_active
        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->get('is_active'));
        }

        // Filter by backup status
        if ($request->filled('backup_status')) {
            $backupStatus = $request->get('backup_status');

            switch ($backupStatus) {
                case 'healthy':
                    $query->whereHas('latestBackupLog', function (Builder $q) {
                        $q->where('status', BackupStatusEnum::completed)
                            ->where('completed_at', '>=', now()->subDay());
                    });
                    break;
                case 'stale':
                    $query->whereHas('latestBackupLog', function (Builder $q) {
                        $q->where('status', BackupStatusEnum::completed)
                            ->where('completed_at', '<', now()->subDay());
                    });
                    break;
                case 'failed':
                    $query->whereHas('latestBackupLog', function (Builder $q) {
                        $q->where('status', BackupStatusEnum::failed);
                    });
                    break;
                case 'none':
                    $query->whereDoesntHave('latestBackupLog');
                    break;
            }
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');

        if ($sortBy === 'backup_status') {
            // Complex sorting for backup status
            $query->leftJoin('backup_logs as latest_logs', function ($join) {
                $join->on('data_sources.id', '=', 'latest_logs.data_source_id')
                    ->whereRaw('latest_logs.id = (
                         SELECT MAX(id) FROM backup_logs
                         WHERE backup_logs.data_source_id = data_sources.id
                     )');
            })
                ->orderByRaw('
                CASE
                    WHEN latest_logs.status = ? AND latest_logs.completed_at >= ? THEN 1
                    WHEN latest_logs.status = ? AND latest_logs.completed_at < ? THEN 2
                    WHEN latest_logs.status = ? THEN 3
                    WHEN latest_logs.status = ? THEN 4
                    ELSE 5
                END '.$sortDirection,
                    [
                        BackupStatusEnum::completed->value, now()->subDay(),
                        BackupStatusEnum::completed->value, now()->subDay(),
                        BackupStatusEnum::running->value,
                        BackupStatusEnum::failed->value,
                    ]
                );
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        // Pagination
        $dataSources = $query->customPaginate();

        // Add backup health status to each data source
        $dataSources->getCollection()->transform(function (DataSource $dataSource) {
            $dataSource->is_backup_healthy = $dataSource->isBackupHealthy();

            return $dataSource;
        });

        return Inertia::render('DataSources/Index', [
            'dataSources' => $dataSources,
        ]);
    }

    /**
     * @param  Request  $request
     * @return Response
     */
    public function create(Request $request): Response
    {
        return Inertia::render('DataSources/Create');
    }

    /**
     * @param  StoreDataSourceRequest  $request
     * @return RedirectResponse
     */
    public function store(StoreDataSourceRequest $request): RedirectResponse
    {

        try {
            $this->authorize('create', DataSource::class);
            $lockName = 'datasource-create-lock';

            return $this->executeStoreWithLock($lockName, function () use ($request) {
                try {
                    DB::beginTransaction();

                    DataSource::create($request->validated());

                    DB::commit();

                    return redirect()->route('data-sources.index')
                        ->with('success', 'Data source created successfully.');
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
            logger()->error("Failed acquire lock when creating Data Source: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withErrors('Failed to acquire lock. Please try again later.');
        } catch (\Throwable $e) {
            logger()->error("Failed to create Data Source: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withErrors($e->getMessage());
        }
    }

    /**
     * @param  Request  $request
     * @param  DataSource  $dataSource
     * @return Response
     */
    public function edit(Request $request, DataSource $dataSource): Response
    {
        return Inertia::render('DataSources/Edit', [
            'dataSource' => $dataSource,
        ]);
    }

    /**
     * @param  UpdateDataSourceRequest  $request
     * @param  DataSource  $dataSource
     * @return RedirectResponse
     */
    public function update(UpdateDataSourceRequest $request, DataSource $dataSource): RedirectResponse
    {
        try {
            $this->authorize('update', $dataSource);
            $lockName = "datasource-update-lock-{$dataSource->id}";

            return $this->executeStoreWithLock($lockName, function () use ($request, $dataSource) {
                try {
                    DB::beginTransaction();

                    $validated = $request->validated();
                    if (empty($validated['password'])) {
                        unset($validated['password']);
                    }

                    $dataSource->update($validated);

                    DB::commit();

                    return redirect()->route('data-sources.index')
                        ->with('success', 'Data source updated successfully.');
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
            logger()->error("Failed acquire lock when updating Data Source: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withErrors('Failed to acquire lock. Please try again later.');
        } catch (\Throwable $e) {
            logger()->error("Failed to update Data Source with ID {$dataSource->id}: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withErrors($e->getMessage());
        }
    }

    /**
     * @param  Request  $request
     * @param  DataSource  $dataSource
     * @return RedirectResponse
     */
    public function destroy(Request $request, DataSource $dataSource): RedirectResponse
    {
        try {
            $this->authorize('delete', $dataSource);
            $lockName = "datasource-destroy-lock-{$dataSource->id}";

            return $this->executeStoreWithLock($lockName, function () use ($dataSource) {
                try {
                    DB::beginTransaction();

                    $dataSource->delete();

                    DB::commit();

                    return redirect()->route('data-sources.index')
                        ->with('success', 'Data source deleted successfully.');
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            });
        } catch (LockTimeoutException $e) {
            logger()->error("Failed acquire lock when deleting Data Source: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withErrors('Failed to acquire lock. Please try again later.');
        } catch (\Throwable $e) {
            logger()->error("Failed to delete Data Source with ID {$dataSource->id}: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withErrors($e->getMessage());
        }
    }

    /**
     * @param  Request  $request
     * @param  DataSource  $dataSource
     * @return JsonResponse
     */
    public function testConnection(Request $request, DataSource $dataSource): JsonResponse
    {
        try {
            $connectionDTO = new ConnectionDTO(
                host: $dataSource->host,
                port: $dataSource->port,
                database: $dataSource->database,
                username: $dataSource->username,
                password: $dataSource->password, // password will be decrypted by the model's cast
                skippedTables: $dataSource->skipped_tables ? preg_split('/\s*,\s*/', $dataSource->skipped_tables) : [],
            );

            $result = $this->backupService->testConnection($connectionDTO);

            if ($result) {
                return response()->json(['success' => true, 'message' => 'Connection successful.']);
            }

            return response()->json(['success' => false, 'message' => 'Connection failed.'], 400);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Test connection with provided data (for forms).
     *
     * @return JsonResponse
     */
    public function testConnectionData(): JsonResponse
    {
        request()->validate([
            'host' => 'required|string',
            'port' => 'required|integer|min:1|max:65535',
            'database' => 'required|string',
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            $connectionDTO = new ConnectionDTO(
                host: request('host'),
                port: request('port'),
                database: request('database'),
                username: request('username'),
                password: request('password'),
            );

            $result = $this->backupService->testConnection($connectionDTO);

            if ($result) {
                return response()->json(['success' => true, 'message' => 'Connection successful.']);
            }

            return response()->json(['success' => false, 'message' => 'Connection failed.'], 400);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Trigger backup for a single data source.
     *
     * @param  Request  $request
     * @param  DataSource  $dataSource
     * @return JsonResponse
     */
    public function backupSingleDB(Request $request, DataSource $dataSource): JsonResponse
    {
        try {
            $this->authorize('view', $dataSource);

            // Check if there's already a running backup for this data source
            $runningBackup = $dataSource->backupLogs()
                ->where('status', BackupStatusEnum::running)
                ->exists();

            if ($runningBackup) {
                return response()->json([
                    'success' => false,
                    'message' => 'A backup is already running for this data source.',
                ], 409);
            }

            // Dispatch backup event
            BackupRequested::dispatch($dataSource);

            return response()->json([
                'success' => true,
                'message' => 'Backup process has been initiated successfully.',
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate backup: '.$e->getMessage(),
            ], 500);
        }
    }
}
