<?php

namespace App\Services;

use App\Enums\BackupStatusEnum;
use App\Exceptions\BackupDestinationException;
use App\Models\BackupLog;
use App\Models\DataSource;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BackupCleanupService
{
    /**
     * @param  string|null  $dataSourceId
     *
     * @throws BackupDestinationException
     * @throws \Throwable
     *
     * @return void
     */
    public function cleanup(?string $dataSourceId = null): void
    {
        if ($dataSourceId) {
            $this->cleanupDataSource($dataSourceId);
        } else {
            $this->cleanupAllDataSources();
        }
    }

    /**
     * @throws BackupDestinationException
     * @throws \Throwable
     *
     * @return void
     */
    private function cleanupAllDataSources(): void
    {
        DataSource::chunk(50, function ($dataSources) {
            foreach ($dataSources as $dataSource) {
                $this->cleanupDataSource($dataSource->id);
            }
        });
    }

    /**
     * @param  string  $dataSourceId
     *
     * @throws BackupDestinationException
     * @throws \Throwable
     *
     * @return void
     */
    private function cleanupDataSource(string $dataSourceId): void
    {
        $config = config('database-backup.cleanup.default_strategy');

        // Get all completed backups for this data source, excluding locked ones
        $backups = BackupLog::where('data_source_id', $dataSourceId)
            ->whereIn('status', [BackupStatusEnum::completed, BackupStatusEnum::partially_failed])
            ->where('locked', false)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($backups->isEmpty()) {
            return;
        }

        // Never delete the newest backup
        $newestBackup = $backups->first();
        $backupsToProcess = $backups->skip(1);

        $backupsToDelete = $this->determineBackupsToDelete($backupsToProcess, $config);

        foreach ($backupsToDelete as $backup) {
            $this->deleteBackup($backup);
        }

        logger()->info('Backup cleanup completed', [
            'data_source_id' => $dataSourceId,
            'total_backups' => $backups->count(),
            'deleted_backups' => $backupsToDelete->count(),
            'newest_backup_preserved' => $newestBackup->id,
        ]);
    }

    /**
     * @param  Collection  $backups
     * @param  array  $config
     * @return Collection
     */
    private function determineBackupsToDelete(Collection $backups, array $config): Collection
    {
        $backupsToDelete = collect();
        $now = Carbon::now();

        // Phase 1: Keep all backups for X days
        $keepAllUntil = $now->copy()->subDays($config['keep_all_backups_for_days']);

        // Phase 2: Keep daily backups for X days after that
        $keepDailyUntil = $keepAllUntil->copy()->subDays($config['keep_daily_backups_for_days']);

        // Phase 3: Keep weekly backups for X weeks after that
        $keepWeeklyUntil = $keepDailyUntil->copy()->subWeeks($config['keep_weekly_backups_for_weeks']);

        // Phase 4: Keep monthly backups for X months after that
        $keepMonthlyUntil = $keepWeeklyUntil->copy()->subMonths($config['keep_monthly_backups_for_months']);

        // Phase 5: Keep yearly backups for X years after that
        $keepYearlyUntil = $keepMonthlyUntil->copy()->subYears($config['keep_yearly_backups_for_years']);

        // Group backups by time periods
        $dailyGroups = collect();
        $weeklyGroups = collect();
        $monthlyGroups = collect();
        $yearlyGroups = collect();

        foreach ($backups as $backup) {
            $backupDate = $backup->created_at;

            if ($backupDate->isAfter($keepAllUntil)) {
                // Keep all backups in this period
                continue;
            } elseif ($backupDate->isAfter($keepDailyUntil)) {
                // Keep one backup per day
                $dayKey = $backupDate->format('Y-m-d');
                if (! $dailyGroups->has($dayKey)) {
                    $dailyGroups->put($dayKey, collect());
                }
                $dailyGroups->get($dayKey)->push($backup);
            } elseif ($backupDate->isAfter($keepWeeklyUntil)) {
                // Keep one backup per week
                $weekKey = $backupDate->format('Y-W');
                if (! $weeklyGroups->has($weekKey)) {
                    $weeklyGroups->put($weekKey, collect());
                }
                $weeklyGroups->get($weekKey)->push($backup);
            } elseif ($backupDate->isAfter($keepMonthlyUntil)) {
                // Keep one backup per month
                $monthKey = $backupDate->format('Y-m');
                if (! $monthlyGroups->has($monthKey)) {
                    $monthlyGroups->put($monthKey, collect());
                }
                $monthlyGroups->get($monthKey)->push($backup);
            } elseif ($backupDate->isAfter($keepYearlyUntil)) {
                // Keep one backup per year
                $yearKey = $backupDate->format('Y');
                if (! $yearlyGroups->has($yearKey)) {
                    $yearlyGroups->put($yearKey, collect());
                }
                $yearlyGroups->get($yearKey)->push($backup);
            } else {
                // Delete backups older than the yearly retention period
                $backupsToDelete->push($backup);
            }
        }

        // For each group, keep the most recent backup and mark others for deletion
        $this->processGroups($dailyGroups, $backupsToDelete);
        $this->processGroups($weeklyGroups, $backupsToDelete);
        $this->processGroups($monthlyGroups, $backupsToDelete);
        $this->processGroups($yearlyGroups, $backupsToDelete);

        return $backupsToDelete;
    }

    /**
     * @param  $groups
     * @param  Collection  $backupsToDelete
     * @return void
     */
    private function processGroups($groups, Collection $backupsToDelete): void
    {
        foreach ($groups as $groupBackups) {
            // Sort by creation date descending and keep the first (most recent)
            $sortedBackups = $groupBackups->sortByDesc('created_at');
            $toKeep = $sortedBackups->first();

            // Mark the rest for deletion
            $toDelete = $sortedBackups->skip(1);
            foreach ($toDelete as $backup) {
                $backupsToDelete->push($backup);
            }
        }
    }

    /**
     * @param  BackupLog  $backup
     *
     * @throws BackupDestinationException
     * @throws \Throwable
     *
     * @return void
     */
    private function deleteBackup(BackupLog $backup): void
    {
        try {
            // Get all files associated with this backup that haven't been deleted yet
            $files = $backup->files()->get();

            if ($files->isEmpty()) {
                logger()->warning('No files to delete for backup', [
                    'backup_id' => $backup->id,
                ]);

                return;
            }

            $deletedFiles = 0;
            $destinationService = app(\App\Services\BackupDestinationService::class);

            // Delete each file using the destination service
            foreach ($files as $file) {
                try {
                    $destinationFromFile = $destinationService->getDestinationFromFile($file);
                    $destinationFromFile->deleteFileRecord($file);
                    $deletedFiles++;
                } catch (\Throwable $fileException) {
                    logger()->error('Failed to delete individual file during cleanup', [
                        'backup_id' => $backup->id,
                        'file_id' => $file->id,
                        'file_path' => $file->path,
                        'error' => $fileException->getMessage(),
                    ]);
                    // Continue with other files even if one fails
                }
            }

            logger()->info('Backup files deleted during cleanup', [
                'backup_id' => $backup->id,
                'data_source_id' => $backup->data_source_id,
                'created_at' => $backup->created_at->toISOString(),
                'status' => $backup->status->value,
                'total_files' => $files->count(),
                'deleted_files' => $deletedFiles,
            ]);

        } catch (\Throwable $exception) {
            logger()->error('Failed to delete backup', [
                'backup_id' => $backup->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
