<?php

namespace App\Jobs;

use App\Services\BackupCleanupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CleanupOldBackupsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public ?string $dataSourceId = null,
    ) {
        $this->tries = config('database-backup.cleanup.tries', 3);
    }

    public function handle(): void
    {
        try {
            logger()->info('Starting backup cleanup job', [
                'data_source_id' => $this->dataSourceId,
            ]);

            $cleanupService = app(BackupCleanupService::class);
            $cleanupService->cleanup($this->dataSourceId);

            logger()->info('Backup cleanup job completed successfully', [
                'data_source_id' => $this->dataSourceId,
            ]);
        } catch (\Throwable $exception) {
            logger()->error('Backup cleanup job encountered an error', [
                'data_source_id' => $this->dataSourceId,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }

    public function failed(\Throwable $exception): void
    {
        logger()->error('Backup cleanup job failed', [
            'data_source_id' => $this->dataSourceId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
