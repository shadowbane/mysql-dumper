<?php

namespace App\Events;

use App\Models\BackupLog;
use Exception;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BackupDestinationFailedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public BackupLog $backupLog,
        public string $destinationId,
        public Exception $exception,
        public int $retryCount = 0,
        public bool $willRetry = false
    ) {}
}
