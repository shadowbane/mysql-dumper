<?php

namespace App\Http\Controllers;

use App\Contracts\BackupServiceInterface;
use App\DTO\ConnectionDTO;
use App\Http\Requests\StoreDataSourceRequest;
use App\Http\Requests\UpdateDataSourceRequest;
use App\Models\DataSource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DataSourceController extends Controller
{
    public function __construct(
        private readonly BackupServiceInterface $backupService
    ) {}

    /**
     * @param  Request  $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        return Inertia::render('DataSources/Index', [
            'dataSources' => DataSource::latest()->paginate(),
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
        DataSource::create($request->validated());

        return redirect()->route('data-sources.index')
            ->with('success', 'Data source created successfully.');
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
        $validated = $request->validated();
        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $dataSource->update($validated);

        return redirect()->route('data-sources.index')
            ->with('success', 'Data source updated successfully.');
    }

    /**
     * @param  Request  $request
     * @param  DataSource  $dataSource
     * @return RedirectResponse
     */
    public function destroy(Request $request, DataSource $dataSource): RedirectResponse
    {
        $dataSource->delete();

        return redirect()->route('data-sources.index')
            ->with('success', 'Data source deleted successfully.');
    }

    /**
     * @param  DataSource  $dataSource
     * @return JsonResponse
     */
    public function testConnection(DataSource $dataSource): JsonResponse
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
}
