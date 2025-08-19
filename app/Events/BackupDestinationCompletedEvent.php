<?php

namespace App\Events;

use App\Models\BackupLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BackupDestinationCompletedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public BackupLog $backupLog,
        public string $destinationId,
        public bool $success,
        public ?string $filePath = null,
        public ?string $errorMessage = null,
        public array $metadata = []
    ) {}
}
