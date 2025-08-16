<?php

namespace App\Listeners;

use App\Enums\BackupStatusEnum;
use App\Enums\BackupTypeEnum;
use App\Events\BackupRequested;
use App\Jobs\CreateBackupJob;
use App\Models\BackupLog;

class HandleBackupRequest
{
    public function handle(BackupRequested $event): void
    {
        // Create backup log entry
        $backupLog = BackupLog::create([
            'data_source_id' => $event->dataSource->id,
            'status' => BackupStatusEnum::pending,
            'type' => BackupTypeEnum::manual,
            'disk' => 'local', // Default disk, can be configurable
        ]);

        // Queue the backup job
        CreateBackupJob::dispatch($backupLog);
    }
}
