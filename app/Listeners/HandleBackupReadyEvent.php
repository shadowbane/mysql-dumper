<?php

namespace App\Listeners;

use App\Contracts\BackupDestinationInterface;
use App\Events\BackupCompletedEvent;
use App\Events\BackupDestinationCompletedEvent;
use App\Events\BackupFailedEvent;
use App\Events\BackupReadyEvent;
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
                if (file_exists($event->temporaryFilePath)) {
                    unlink($event->temporaryFilePath);
                }

                return;
            }

            // Mark as storing to destinations
            $event->backupLog->markAsStoringToDestinations(
                array_map(fn(BackupDestinationInterface $dest) => $dest->getDestinationId(), $destinations)
            );

            // Store to each destination
            $successes = 0;
            $failures = 0;

            foreach ($destinations as $destination) {
                try {
                    $filePath = $destination->store(
                        $event->backupLog,
                        $event->temporaryFilePath,
                        $event->filename,
                        $event->metadata
                    );

                    if ($filePath) {
                        $event->backupLog->recordDestinationCompletion(
                            $destination->getDestinationId(),
                            true,
                            $filePath
                        );
                        $successes++;

                        // Emit individual destination completed event
                        BackupDestinationCompletedEvent::dispatch(
                            $event->backupLog,
                            $destination->getDestinationId(),
                            true,
                            $filePath
                        );
                    } else {
                        throw new Exception('Destination returned null file path');
                    }
                } catch (Exception $e) {
                    $event->backupLog->recordDestinationCompletion(
                        $destination->getDestinationId(),
                        false,
                        null,
                        $e->getMessage()
                    );
                    $failures++;

                    // Emit individual destination completed event
                    BackupDestinationCompletedEvent::dispatch(
                        $event->backupLog,
                        $destination->getDestinationId(),
                        false,
                        null,
                        $e->getMessage()
                    );
                }
            }

            // Determine final status
            if ($successes > 0 && $failures === 0) {
                // All destinations succeeded
                $event->backupLog->markAsCompleted(
                    array_merge($event->metadata, [
                        'destinations_succeeded' => $successes,
                        'destinations_failed' => $failures,
                        'total_destinations' => count($destinations),
                    ])
                );

                // Emit backup completed event
                BackupCompletedEvent::dispatch(
                    $event->backupLog,
                    $event->filename,
                    $event->fileSize,
                    array_merge($event->metadata, [
                        'destinations_succeeded' => $successes,
                        'destinations_failed' => $failures,
                        'total_destinations' => count($destinations),
                    ])
                );
            } elseif ($successes > 0) {
                // Some destinations succeeded, some failed - mark as partially failed
                $event->backupLog->markAsPartiallyFailed(
                    array_merge($event->metadata, [
                        'destinations_succeeded' => $successes,
                        'destinations_failed' => $failures,
                        'total_destinations' => count($destinations),
                    ])
                );

                $event->backupLog->addWarning([
                    'message' => "Some destinations failed: {$failures} out of ".count($destinations),
                    'destinations_succeeded' => $successes,
                    'destinations_failed' => $failures,
                ]);
            } else {
                // All destinations failed
                $exception = new Exception("All backup destinations failed: {$failures} out of ".count($destinations));
                $event->backupLog->markAsFailed($exception);

                // Emit backup failed event
                BackupFailedEvent::dispatch($event->backupLog, $exception);
            }

        } catch (Exception $e) {
            $event->backupLog->markAsFailed($e);

            // Emit backup failed event
            BackupFailedEvent::dispatch($event->backupLog, $e);
        } finally {
            // Clean up temporary file
            if (file_exists($event->temporaryFilePath)) {
                unlink($event->temporaryFilePath);
            }
        }
    }
}
