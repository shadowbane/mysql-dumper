<?php

namespace App\Jobs;

use App\Contracts\BackupServiceInterface;
use App\DTO\ConnectionDTO;
use App\Models\BackupLog;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CreateBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public BackupLog $backupLog
    ) {}

    public function handle(BackupServiceInterface $backupService): void
    {
        try {
            // Mark backup as running
            $this->backupLog->markAsRunning();

            $dataSource = $this->backupLog->dataSource;

            // Create connection DTO
            $connectionDTO = new ConnectionDTO(
                host: $dataSource->host,
                port: $dataSource->port,
                database: $dataSource->database,
                username: $dataSource->username,
                password: $dataSource->password,
                skippedTables: $dataSource->skipped_tables ? preg_split('/\s*,\s*/', $dataSource->skipped_tables) : [],
                structureOnly: $dataSource->structure_only ? preg_split('/\s*,\s*/', $dataSource->structure_only) : []
            );

            // Set backup service disk and path
            $backupService->setDisk($this->backupLog->disk);

            // Perform backup
            $filePath = $backupService->backup($connectionDTO);

            // Get file info for backup log
            $filename = basename($filePath);
            $fileSize = Storage::disk($this->backupLog->disk)->size($filePath);

            // Mark backup as completed
            $this->backupLog->markAsCompleted(
                filename: $filename,
                filePath: $filePath,
                fileSize: $fileSize,
                metadata: [
                    'database' => $dataSource->database,
                    'tables_backed_up' => count($connectionDTO->skippedTables ? array_diff($backupService->getTables($connectionDTO), $connectionDTO->skippedTables) : $backupService->getTables($connectionDTO)),
                    'structure_only_tables' => $connectionDTO->structureOnly,
                    'skipped_tables' => $connectionDTO->skippedTables,
                ]
            );

        } catch (Exception $e) {
            // Mark backup as failed
            $this->backupLog->markAsFailed($e->getMessage());

            // Re-throw to trigger job failure handling
            throw $e;
        }
    }

    public function failed(Exception $exception): void
    {
        // Ensure backup log is marked as failed if job fails
        if ($this->backupLog->status !== \App\Enums\BackupStatusEnum::failed) {
            $this->backupLog->markAsFailed($exception->getMessage());
        }
    }
}
