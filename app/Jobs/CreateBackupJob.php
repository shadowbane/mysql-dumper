<?php

namespace App\Jobs;

use App\Contracts\BackupServiceInterface;
use App\Models\BackupLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // Disable Laravel's built-in retries - we handle retries at destination level
    public int $timeout = 3600; // 1 hour timeout

    public function __construct(
        public BackupLog $backupLog
    ) {}

    /**
     * @throws \Throwable
     */
    public function handle(BackupServiceInterface $backupService): void
    {
        // Refresh backup log to get latest status
        $this->backupLog->refresh();

        // Check if backup is already running, completed, or failed
        if (in_array($this->backupLog->status, [
            \App\Enums\BackupStatusEnum::running,
            \App\Enums\BackupStatusEnum::backup_ready,
            \App\Enums\BackupStatusEnum::storing_to_destinations,
            \App\Enums\BackupStatusEnum::completed,
            \App\Enums\BackupStatusEnum::partially_failed,
            \App\Enums\BackupStatusEnum::failed,
        ])) {
            logger()->info('Backup job skipped - already processed', [
                'backup_log_id' => $this->backupLog->id,
                'current_status' => $this->backupLog->status->value,
            ]);

            return;
        }

        $backupService->runBackupProcess($this->backupLog);
    }

    public function failed(\Throwable $exception): void
    {
        // Ensure backup log is marked as failed if job fails
        if ($this->backupLog->status !== \App\Enums\BackupStatusEnum::failed) {
            $this->backupLog->markAsFailed($exception);
        }
    }
}
