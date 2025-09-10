<?php

namespace App\Listeners;

use App\Enums\BackupStatusEnum;
use App\Events\BackupDestinationRetryEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class HandleBackupDestinationRetryEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(BackupDestinationRetryEvent $event): void
    {
        // Log the retry attempt
        logger()->warning('Retrying backup destination after failure', [
            'backup_log_id' => $event->backupLog->id,
            'destination_id' => $event->destinationId,
            'retry_count' => $event->retryCount,
            'last_error' => $event->lastExceptionMessage,
        ]);

        // Update timeline to show we're retrying
        $timeline = $event->backupLog->timelines()
            ->where('status', BackupStatusEnum::storing_to_destinations)
            ->whereJsonContains('metadata->destination_id', $event->destinationId)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($timeline) {
            $multiplier = app()->environment() === 'production' ? 60 : 1; // multiply by 60s.
            $existingMetadata = $timeline->metadata ?? [];
            $updatedMetadata = array_merge($existingMetadata, [
                'retry_scheduled' => true,
                'retry_count' => $event->retryCount,
                'scheduled_at' => now()->toISOString(),
                'last_error' => $event->lastExceptionMessage,
                'delay_seconds' => pow(2, $event->retryCount - 1) * $multiplier, // Exponential backoff
                'will_retry' => true,
            ]);

            $timeline->update([
                'metadata' => $updatedMetadata,
                'updated_at' => now(),
            ]);
        }

        // The actual retry job dispatch is handled in StoreBackupToDestinationJob
        // This listener is mainly for logging and timeline tracking
    }
}
