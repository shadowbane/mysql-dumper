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

    public function __construct(
        public BackupLog $backupLog
    ) {}

    /**
     * @throws \Throwable
     */
    public function handle(BackupServiceInterface $backupService): void
    {
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
