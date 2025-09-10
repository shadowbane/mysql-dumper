<?php

namespace App\Events;

use App\DTO\BackupFileDTO;
use App\Models\BackupLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AllDestinationsProcessedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public BackupLog $backupLog,
        public BackupFileDTO $fileData,
        public array $results = [] // ['destination_id' => ['success' => bool, 'file_path' => string|null, 'error' => string|null]]
    ) {}
}
