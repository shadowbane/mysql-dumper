<?php

namespace App\Console\Commands;

use App\Jobs\CleanupOldBackupsJob;
use App\Models\DataSource;
use Illuminate\Console\Command;

class CleanupOldBackupsCommand extends Command
{
    protected $signature = 'backups:cleanup
                            {--data-source= : Cleanup backups for a specific data source ID}
                            {--dry-run : Show what backups would be deleted without actually deleting them}
                            {--queue : Run cleanup via queue instead of synchronously}';
    protected $description = 'Clean up old backup files according to the retention policy';

    public function handle()
    {
        $dataSourceId = $this->option('data-source');
        $isDryRun = $this->option('dry-run');
        $useQueue = $this->option('queue');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE: No backups will actually be deleted');
        }

        if ($dataSourceId) {
            $dataSource = DataSource::find($dataSourceId);
            if (! $dataSource) {
                $this->error("Data source with ID {$dataSourceId} not found.");

                return 1;
            }

            $this->info("Cleaning up backups for data source: {$dataSource->name}");

            if ($isDryRun) {
                $this->performDryRunCleanup($dataSourceId);
            } elseif ($useQueue) {
                CleanupOldBackupsJob::dispatch($dataSourceId);
                $this->info('Cleanup job dispatched to queue');
            } else {
                app(\App\Services\BackupCleanupService::class)->cleanup($dataSourceId);
                $this->info('Cleanup completed');
            }
        } else {
            $this->info('Cleaning up backups for all data sources');

            if ($isDryRun) {
                $this->performDryRunCleanup();
            } elseif ($useQueue) {
                CleanupOldBackupsJob::dispatch();
                $this->info('Cleanup job dispatched to queue');
            } else {
                app(\App\Services\BackupCleanupService::class)->cleanup();
                $this->info('Cleanup completed');
            }
        }

        return 0;
    }

    private function performDryRunCleanup(?string $dataSourceId = null): void
    {
        $this->info('Performing dry run analysis...');

        // Mock the cleanup service to show what would be deleted
        $command = $this;
        $cleanupService = new class($command) {
            public $command;

            public function __construct($command)
            {
                $this->command = $command;
            }

            public function cleanup(?string $dataSourceId = null): void
            {
                if ($dataSourceId) {
                    $this->analyzeDataSource($dataSourceId);
                } else {
                    DataSource::chunk(50, function ($dataSources) {
                        foreach ($dataSources as $dataSource) {
                            $this->analyzeDataSource($dataSource->id);
                        }
                    });
                }
            }

            private function analyzeDataSource(string $dataSourceId): void
            {
                $config = config('database-backup.cleanup.default_strategy');

                $backups = \App\Models\BackupLog::where('data_source_id', $dataSourceId)
                    ->whereIn('status', [\App\Enums\BackupStatusEnum::completed, \App\Enums\BackupStatusEnum::partially_failed])
                    ->where('locked', false)
                    ->orderBy('created_at', 'desc')
                    ->get();

                if ($backups->isEmpty()) {
                    return;
                }

                $dataSource = DataSource::find($dataSourceId);
                $this->command?->line("Data Source: {$dataSource->name} (ID: {$dataSourceId})");
                $this->command?->line("  Total backups: {$backups->count()}");

                $newestBackup = $backups->first();
                $this->command?->line("  Newest backup: {$newestBackup->created_at} (PROTECTED)");

                $lockedCount = \App\Models\BackupLog::where('data_source_id', $dataSourceId)
                    ->where('locked', true)
                    ->count();

                if ($lockedCount > 0) {
                    $this->command?->line("  Locked backups: {$lockedCount} (PROTECTED)");
                }

                // Show retention periods
                $now = \Carbon\Carbon::now();
                $this->command?->line('  Retention policy:');
                $this->command?->line("    - Keep all backups for {$config['keep_all_backups_for_days']} days");
                $this->command?->line("    - Keep daily backups for {$config['keep_daily_backups_for_days']} days");
                $this->command?->line("    - Keep weekly backups for {$config['keep_weekly_backups_for_weeks']} weeks");
                $this->command?->line("    - Keep monthly backups for {$config['keep_monthly_backups_for_months']} months");
                $this->command?->line("    - Keep yearly backups for {$config['keep_yearly_backups_for_years']} years");

                // Count how many would be in each category
                $keepAllUntil = $now->copy()->subDays($config['keep_all_backups_for_days']);
                $keepDailyUntil = $keepAllUntil->copy()->subDays($config['keep_daily_backups_for_days']);

                $wouldDelete = $backups->skip(1)->filter(function ($backup) use ($keepDailyUntil) {
                    return $backup->created_at->isBefore($keepDailyUntil);
                })->count();

                $this->command?->line("  Would delete: {$wouldDelete} backups");
                $this->command?->line('');
            }
        };

        $cleanupService->cleanup($dataSourceId);
    }
}
