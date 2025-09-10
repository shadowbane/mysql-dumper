<?php

namespace App\Events;

use App\DTO\BackupFileDTO;
use App\Models\BackupLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BackupDestinationRetryEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public BackupLog $backupLog,
        public string $destinationId,
        public string $lastExceptionMessage,
        public int $retryCount,
        public BackupFileDTO $fileData,
        public array $metadata = []
    ) {}
}
