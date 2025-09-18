<?php

namespace App\Listeners;

use App\Contracts\BackupDestinationInterface;
use App\Enums\BackupStatusEnum;
use App\Events\BackupFailedEvent;
use App\Events\BackupReadyEvent;
use App\Jobs\StoreBackupToDestinationJob;
use App\Services\BackupDestinationService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleBackupReadyEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private BackupDestinationService $backupDestinationService
    ) {}

    public function handle(BackupReadyEvent $event): void
    {
        try {
            // Get all available backup destinations
            $destinations = $this->backupDestinationService->getEnabledDestinations($event->backupLog);

            if (empty($destinations)) {
                // No destinations configured, mark as failed
                $exception = new Exception('No backup destinations configured');
                $event->backupLog->markAsFailed($exception);

                // Emit backup failed event
                BackupFailedEvent::dispatch($event->backupLog, $exception);

                // Clean up temporary file
                if ($event->fileData->temporaryDirectory->exists()) {
                    $event->fileData->temporaryDirectory->delete();
                }

                return;
            }

            // Create initial timeline entry for storing to destinations phase
            $event->backupLog->recordStatusChange($event->backupLog->status, [
                'storing_phase_started' => now()->toISOString(),
                'total_destinations' => count($destinations),
                'destinations_list' => array_map(fn(BackupDestinationInterface $dest) => $dest->getDestinationId(), $destinations),
            ]);

            // Dispatch individual jobs for each destination
            foreach ($destinations as $destination) {
                $destinationId = $destination->getDestinationId();

                // Create initial timeline entry for this destination (queued state)
                $event->backupLog->timelines()->create([
                    'status' => BackupStatusEnum::storing_to_destinations,
                    'metadata' => [
                        'destination_id' => $destinationId,
                        'has_started' => false,
                        'queued_at' => now()->toISOString(),
                        'retries' => 0,
                    ],
                ]);

                // Dispatch the job for this destination
                StoreBackupToDestinationJob::dispatch(
                    $event->backupLog->id,
                    $destinationId,
                    $event->fileData,
                    $event->metadata
                );
            }

        } catch (Exception $e) {
            logger()->error("Backup Failed: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            $event->backupLog->markAsFailed($e);

            // Emit backup failed event
            BackupFailedEvent::dispatch($event->backupLog, $e);

            // Clean up temporary file immediately if we failed to dispatch jobs
            if ($event->fileData->temporaryDirectory->exists()) {
                $event->fileData->temporaryDirectory->delete();
            }
        }
    }
}
