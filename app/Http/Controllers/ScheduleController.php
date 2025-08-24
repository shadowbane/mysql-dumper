<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Models\DataSource;
use App\Models\Schedule;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    /**
     * Display a listing of the schedules.
     *
     * @param  Request  $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $query = Schedule::with(['dataSources', 'backupLogs' => function ($query) {
            $query->latest()->limit(1);
        }]);

        // Search
        if ($request->filled('search')) {
            $searchTerm = $request->get('search');
            $query->where(function (Builder $q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        // Filter by status
        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->get('is_active'));
        }

        // Filter by data source
        if ($request->filled('data_source_id')) {
            $query->whereHas('dataSources', function (Builder $q) use ($request) {
                $q->where('data_sources.id', $request->get('data_source_id'));
            });
        }

        // Sorting
        $sortBy = $request->get('sort', 'name');
        $direction = $request->get('direction', 'asc');

        if (in_array($sortBy, ['name', 'hour', 'minute', 'is_active', 'created_at', 'last_run_at'])) {
            $query->orderBy($sortBy, $direction);
        } else {
            $query->orderBy('name', 'asc');
        }

        // Pagination
        $schedules = $query->customPaginate();

        // Get all data sources for filtering
        $dataSources = DataSource::select('id', 'name')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Schedules/Index', [
            'schedules' => $schedules,
            'dataSources' => $dataSources,
        ]);
    }

    /**
     * Show the form for creating a new schedule.
     *
     * @param  Request  $request
     * @return Response
     */
    public function create(Request $request): Response
    {
        $dataSources = DataSource::select('id', 'name', 'host', 'database')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Schedules/Create', [
            'dataSources' => $dataSources,
        ]);
    }

    /**
     * Store a newly created schedule in storage.
     *
     * @param  StoreScheduleRequest  $request
     * @return RedirectResponse
     */
    public function store(StoreScheduleRequest $request): RedirectResponse
    {
        try {
            $lockName = 'schedule-create-lock';

            return $this->executeStoreWithLock($lockName, function () use ($request) {
                try {
                    DB::beginTransaction();

                    $validated = $request->validated();
                    $dataSourceIds = $validated['data_source_ids'];
                    unset($validated['data_source_ids']);

                    $schedule = Schedule::create($validated);
                    $schedule->dataSources()->sync($dataSourceIds);

                    DB::commit();

                    return redirect()->route('schedules.index')
                        ->with('success', 'Schedule created successfully.');
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            });
        } catch (LockTimeoutException $e) {
            logger()->error("Failed acquire lock when creating Schedule: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withErrors('Failed to acquire lock. Please try again later.');
        } catch (\Throwable $e) {
            logger()->error("Failed to create Schedule: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withErrors($e->getMessage());
        }
    }

    /**
     * Display the specified schedule.
     *
     * @param  Request  $request
     * @param  Schedule  $schedule
     * @return Response
     */
    public function show(Request $request, Schedule $schedule): Response
    {
        $schedule->load([
            'dataSources',
            'backupLogs' => function ($query) {
                $query->with('dataSource')->latest()->limit(10);
            },
        ]);

        return Inertia::render('Schedules/Show', [
            'schedule' => $schedule,
        ]);
    }

    /**
     * Show the form for editing the specified schedule.
     *
     * @param  Request  $request
     * @param  Schedule  $schedule
     * @return Response
     */
    public function edit(Request $request, Schedule $schedule): Response
    {
        $schedule->load('dataSources');

        $dataSources = DataSource::select('id', 'name', 'host', 'database')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Schedules/Edit', [
            'schedule' => $schedule,
            'dataSources' => $dataSources,
        ]);
    }

    /**
     * Update the specified schedule in storage.
     *
     * @param  UpdateScheduleRequest  $request
     * @param  Schedule  $schedule
     * @return RedirectResponse
     */
    public function update(UpdateScheduleRequest $request, Schedule $schedule): RedirectResponse
    {
        try {
            $lockName = "schedule-update-lock-{$schedule->id}";

            return $this->executeStoreWithLock($lockName, function () use ($request, $schedule) {
                try {
                    DB::beginTransaction();

                    $validated = $request->validated();
                    $dataSourceIds = $validated['data_source_ids'];
                    unset($validated['data_source_ids']);

                    $schedule->update($validated);
                    $schedule->dataSources()->sync($dataSourceIds);

                    DB::commit();

                    return redirect()->route('schedules.index')
                        ->with('success', 'Schedule updated successfully.');
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            });
        } catch (LockTimeoutException $e) {
            logger()->error("Failed acquire lock when updating Schedule: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withErrors('Failed to acquire lock. Please try again later.');
        } catch (\Throwable $e) {
            logger()->error("Failed to update Schedule with ID {$schedule->id}: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withErrors($e->getMessage());
        }
    }

    /**
     * Remove the specified schedule from storage.
     *
     * @param  Request  $request
     * @param  Schedule  $schedule
     * @return RedirectResponse
     */
    public function destroy(Request $request, Schedule $schedule): RedirectResponse
    {
        try {
            $lockName = "schedule-destroy-lock-{$schedule->id}";

            return $this->executeStoreWithLock($lockName, function () use ($schedule) {
                try {
                    DB::beginTransaction();

                    // Note: Related backup logs will have their schedule_id set to null
                    // due to the foreign key constraint with nullOnDelete()
                    $schedule->delete();

                    DB::commit();

                    return redirect()->route('schedules.index')
                        ->with('success', 'Schedule deleted successfully.');
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            });
        } catch (LockTimeoutException $e) {
            logger()->error("Failed acquire lock when deleting Schedule: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withErrors('Failed to acquire lock. Please try again later.');
        } catch (\Throwable $e) {
            logger()->error("Failed to delete Schedule with ID {$schedule->id}: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withErrors($e->getMessage());
        }
    }
}
