<?php

namespace App\Console\Commands;

use App\Enums\BackupTypeEnum;
use App\Jobs\CreateBackupJob;
use App\Models\BackupLog;
use App\Models\Schedule;
use Illuminate\Console\Command;

class DispatchScheduledBackupsCommand extends Command
{
    protected $signature = 'backups:dispatch-scheduled
                            {--dry-run : Show what would be scheduled without actually dispatching jobs}';
    protected $description = 'Dispatch backup jobs for schedules that should run now';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        $this->info('Checking for scheduled backups that should run now...');

        $activeSchedules = Schedule::where('is_active', true)
            ->with('dataSources')
            ->get();

        if ($activeSchedules->isEmpty()) {
            $this->info('No active schedules found.');

            return 0;
        }

        $schedulesToRun = $activeSchedules->filter(function (Schedule $schedule) {
            return $schedule->shouldRunNow();
        });

        if ($schedulesToRun->isEmpty()) {
            $this->info('No schedules need to run at this time.');

            return 0;
        }

        $totalJobsDispatched = 0;

        foreach ($schedulesToRun as $schedule) {
            $this->info("Processing schedule: {$schedule->name}");

            if ($schedule->dataSources->isEmpty()) {
                $this->warn("  No data sources configured for schedule '{$schedule->name}', skipping.");

                continue;
            }

            foreach ($schedule->dataSources as $dataSource) {
                $this->line("  - Dispatching backup for data source: {$dataSource->name}");

                if (! $isDryRun) {
                    $backupLog = BackupLog::create([
                        'data_source_id' => $dataSource->id,
                        'schedule_id' => $schedule->id,
                        'status' => \App\Enums\BackupStatusEnum::pending,
                        'type' => BackupTypeEnum::automated,
                    ]);

                    CreateBackupJob::dispatch($backupLog);
                    $totalJobsDispatched++;
                } else {
                    $this->line("  [DRY RUN] Would dispatch backup job for {$dataSource->name}");
                    $totalJobsDispatched++;
                }
            }

            if (! $isDryRun) {
                $schedule->markAsRun();
                $this->line("  Schedule '{$schedule->name}' marked as run.");
            }
        }

        if ($isDryRun) {
            $this->info("DRY RUN: Would have dispatched {$totalJobsDispatched} backup jobs for ".count($schedulesToRun).' schedule(s).');
        } else {
            $this->info("Successfully dispatched {$totalJobsDispatched} backup jobs for ".count($schedulesToRun).' schedule(s).');
        }

        return 0;
    }
}
