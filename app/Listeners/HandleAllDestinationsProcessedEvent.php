<?php

namespace App\Listeners;

use App\Enums\BackupStatusEnum;
use App\Events\AllDestinationsProcessedEvent;
use App\Events\BackupCompletedEvent;
use App\Events\BackupFailedEvent;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleAllDestinationsProcessedEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(AllDestinationsProcessedEvent $event): void
    {
        $backupLog = $event->backupLog;
        $results = $event->results;

        try {
            // Count successes and failures
            $successes = 0;
            $failures = 0;
            $successfulDestinations = [];
            $failedDestinations = [];

            foreach ($results as $destinationId => $result) {
                if ($result['success']) {
                    $successes++;
                    $successfulDestinations[] = $destinationId;
                } else {
                    $failures++;
                    $failedDestinations[] = [
                        'destination_id' => $destinationId,
                        'error' => $result['error'],
                    ];
                }
            }

            $totalDestinations = count($results);

            // Prepare metadata
            $metadata = [
                'destinations_succeeded' => $successes,
                'destinations_failed' => $failures,
                'total_destinations' => $totalDestinations,
                'successful_destinations' => $successfulDestinations,
                'failed_destinations' => $failedDestinations,
            ];

            // Determine final status
            if ($successes > 0 && $failures === 0) {
                // All destinations succeeded - update directly without creating duplicate timeline
                $backupLog->update([
                    'status' => BackupStatusEnum::completed,
                    'metadata' => array_merge($backupLog->metadata ?? [], $metadata),
                    'completed_at' => now(),
                ]);

                // Create single timeline entry
                $backupLog->recordStatusChange(BackupStatusEnum::completed, [
                    'completed_at' => now()->toISOString(),
                    'backup_metadata' => array_merge($backupLog->metadata ?? [], $metadata),
                ]);

                // Get file information from the backup ready timeline
                $backupReadyTimeline = $backupLog->timelines()
                    ->where('status', 'backup_ready')
                    ->orderBy('created_at', 'desc')
                    ->first();

                $filename = 'backup_file'; // Default fallback
                $fileSize = 0;

                if ($backupReadyTimeline && isset($backupReadyTimeline->metadata['backup_metadata'])) {
                    $backupMetadata = $backupReadyTimeline->metadata['backup_metadata'];
                    // You might need to adjust this based on how filename/size is stored
                }

                // Emit backup completed event
                BackupCompletedEvent::dispatch(
                    $backupLog,
                    $filename,
                    $fileSize,
                    $metadata
                );

                logger()->debug('Backup completed successfully to all destinations', [
                    'backup_log_id' => $backupLog->id,
                    'successful_destinations' => $successfulDestinations,
                    'total_destinations' => $totalDestinations,
                ]);

            } elseif ($successes > 0) {
                // Some destinations succeeded, some failed - update directly without creating duplicate timeline
                $backupLog->update([
                    'status' => BackupStatusEnum::partially_failed,
                    'metadata' => array_merge($backupLog->metadata ?? [], $metadata),
                    'completed_at' => now(),
                ]);

                // Create single timeline entry
                $backupLog->recordStatusChange(BackupStatusEnum::partially_failed, [
                    'partially_failed_at' => now()->toISOString(),
                    'backup_metadata' => array_merge($backupLog->metadata ?? [], $metadata),
                ]);

                $backupLog->addWarning([
                    'message' => "Some destinations failed: {$failures} out of {$totalDestinations}",
                    'destinations_succeeded' => $successes,
                    'destinations_failed' => $failures,
                    'failed_destinations' => $failedDestinations,
                ]);

                logger()->warning('Backup partially failed', [
                    'backup_log_id' => $backupLog->id,
                    'successful_destinations' => $successfulDestinations,
                    'failed_destinations' => $failedDestinations,
                    'successes' => $successes,
                    'failures' => $failures,
                ]);

            } else {
                // All destinations failed
                $exception = new Exception("All backup destinations failed: {$failures} out of {$totalDestinations}");
                $backupLog->markAsFailed($exception);

                // Emit backup failed event
                BackupFailedEvent::dispatch($backupLog, $exception);

                logger()->error('Backup failed to all destinations', [
                    'backup_log_id' => $backupLog->id,
                    'failed_destinations' => $failedDestinations,
                    'total_destinations' => $totalDestinations,
                ]);
            }

        } catch (Exception $e) {
            logger()->error('Error processing all destinations completion', [
                'backup_log_id' => $backupLog->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $backupLog->markAsFailed($e);
            BackupFailedEvent::dispatch($backupLog, $e);
        } finally {
            // Clean up temporary file - all destinations have been processed
            if (file_exists($event->fileData->fullPath)) {
                @unlink($event->fileData->fullPath);
                logger()->debug('Cleaned up temporary backup file', [
                    'backup_log_id' => $backupLog->id,
                    'file_path' => $event->fileData->fullPath,
                ]);
            }

            // Also try to clean up the directory if it exists and is empty
            if ($event->fileData->temporaryDirectory->exists()) {
                $event->fileData->temporaryDirectory->delete();
            }
        }
    }
}
