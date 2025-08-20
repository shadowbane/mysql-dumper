<?php

namespace App\Events;

use App\Models\BackupLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BackupCompletedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public BackupLog $backupLog,
        public string $filename,
        public int $fileSize,
        public array $metadata = []
    ) {}
}
