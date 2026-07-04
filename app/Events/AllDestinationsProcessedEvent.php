<?php

namespace App\Events;

use App\DTO\BackupFileDTO;
use App\DTO\DestinationResultDTO;
use App\Models\BackupLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Spatie\LaravelData\DataCollection;

class AllDestinationsProcessedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  DataCollection<int, DestinationResultDTO>  $results
     */
    public function __construct(
        public BackupLog $backupLog,
        public BackupFileDTO $fileData,
        public DataCollection $results,
    ) {}
}
