<?php

namespace App\Events;

use App\Models\BackupLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BackupFailedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public BackupLog $backupLog,
        public \Exception $exception
    ) {}
}
