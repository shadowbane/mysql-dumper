<?php

namespace App\Http\Controllers;

use App\Enums\BackupStatusEnum;
use App\Models\BackupLog;
use App\Models\DataSource;
use App\Models\File;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * @param  Request  $request
     * @return \Inertia\Response
     */
    public function index(Request $request): \Inertia\Response
    {
        return Inertia::render('Dashboard', [
            'stats' => fn() => $this->getStats(),
            'recentBackups' => fn() => $this->getRecentBackups(),
            'activeDataSources' => fn() => $this->getActiveDataSources(),
        ]);
    }

    /**
     * @return Collection
     */
    private function getRecentBackups(): Collection
    {
        // Recent Backups Table
        return BackupLog::with('dataSource:id,name')
            ->when(! auth()->user()->isAdministrator(), function (Builder $query) {
                $query->whereIn('backup_logs.data_source_id', auth()->user()->dataSources->pluck('id'));
            })
            ->latest()
            ->take(5)
            ->get()
            ->map(fn(BackupLog $log) => [
                'id' => $log->id,
                'type' => $log->type,
                'status' => $log->status->value,
                'human_size' => $log->human_size,
                'created_at' => $log->created_at,
                'is_file_available' => $log->isFileAvailable(),
                'data_source' => $log->dataSource,
                'locked' => $log->locked,
            ]);
    }

    /**
     * @return Collection
     */
    private function getStats(): Collection
    {
        // Card Metrics
        $totalDataSources = DataSource::query()
            ->when(! auth()->user()->isAdministrator(), function (Builder $query) {
                $query->whereIn('id', auth()->user()->dataSources->pluck('id'));
            })
            ->count();
        $newSourcesLastWeek = DataSource::where('created_at', '>=', now()->subWeek())
            ->when(! auth()->user()->isAdministrator(), function (Builder $query) {
                $query->whereIn('id', auth()->user()->dataSources->pluck('id'));
            })
            ->count();

        $currentBackupIds = BackupLog::whereIn('status', [BackupStatusEnum::completed, BackupStatusEnum::partially_failed])
            ->when(! auth()->user()->isAdministrator(), function (Builder $query) {
                $query->whereIn('backup_logs.data_source_id', auth()->user()->dataSources->pluck('id'));
            })
            ->pluck('id');
        $totalStorageUsedData = File::where('fileable_type', BackupLog::class)
            ->whereIn('fileable_id', $currentBackupIds)
            ->groupBy('fileable_id')
            ->selectRaw('fileable_id, max(`size_bytes`) as aggregate')
            ->get();
        $totalStorageUsed = $totalStorageUsedData->sum('aggregate') ?? 0;

        $lastMonthStorageUse = BackupLog::where('created_at', '<=', now()->subMonth())
            ->when(! auth()->user()->isAdministrator(), function (Builder $query) {
                $query->whereIn('backup_logs.data_source_id', auth()->user()->dataSources->pluck('id'));
            })
            ->where('created_at', '>=', now()->subMonths(2))
            ->where('status', BackupStatusEnum::completed)
            ->pluck('id');
        $lastMonthStorageUseData = File::where('fileable_type', BackupLog::class)
            ->whereIn('fileable_id', $lastMonthStorageUse)
            ->groupBy('fileable_id')
            ->selectRaw('fileable_id, max(`size_bytes`) as aggregate')
            ->get();
        $lastMonthStorageUsed = $lastMonthStorageUseData->sum('aggregate') ?? 0;

        $backupsThisMonth = BackupLog::query()
            ->when(! auth()->user()->isAdministrator(), function (Builder $query) {
                $query->whereIn('backup_logs.data_source_id', auth()->user()->dataSources->pluck('id'));
            })
            ->where('created_at', '>=', now()->startOfMonth())->count();
        $backupsLastMonth = BackupLog::query()
            ->when(! auth()->user()->isAdministrator(), function (Builder $query) {
                $query->whereIn('backup_logs.data_source_id', auth()->user()->dataSources->pluck('id'));
            })
            ->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])->count();

        $recentFailures = BackupLog::where('status', BackupStatusEnum::failed)
            ->when(! auth()->user()->isAdministrator(), function (Builder $query) {
                $query->whereIn('backup_logs.data_source_id', auth()->user()->dataSources->pluck('id'));
            })
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return collect([
            'totalDataSources' => [
                'count' => $totalDataSources,
                'comparison' => $newSourcesLastWeek,
            ],
            'storageUsed' => [
                'count' => $this->formatBytes($totalStorageUsed),
                'comparison' => $this->calculatePercentageChange($totalStorageUsed, $lastMonthStorageUsed),
            ],
            'backupsThisMonth' => [
                'count' => $backupsThisMonth,
                'comparison' => $this->calculatePercentageChange($backupsThisMonth, $backupsLastMonth),
            ],
            'recentFailures' => [
                'count' => $recentFailures,
            ],
        ]);
    }

    /**
     * @param  $bytes
     * @param  $precision
     * @return string
     */
    private function formatBytes($bytes, $precision = 2): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $base = log($bytes, 1024);
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];

        return round(1024 ** ($base - floor($base)), $precision).' '.$suffixes[floor($base)];
    }

    /**
     * @return Collection
     */
    private function getActiveDataSources(): Collection
    {
        // Active Data Sources Card
        return DataSource::with('latestBackupLog:id,status,data_source_id')
            ->where('is_active', true)
            ->when(! auth()->user()->isAdministrator(), function (Builder $query) {
                $query->whereIn('id', auth()->user()->dataSources->pluck('id'));
            })
            ->latest()
            ->take(5)
            ->get(['id', 'name', 'host'])
            ->map(fn(DataSource $dataSource) => [
                'id' => $dataSource->id,
                'name' => $dataSource->name,
                'host' => $dataSource->host,
                'latest_backup_log' => $dataSource->latestBackupLog,
            ]);
    }

    private function calculatePercentageChange(int $current, int $previous): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        $percentage = (($current - $previous) / $previous) * 100;

        return round($percentage, 2);
    }
}
