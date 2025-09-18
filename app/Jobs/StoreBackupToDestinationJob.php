<?php

namespace App\Jobs;

use App\Contracts\BackupDestinationInterface;
use App\DTO\BackupFileDTO;
use App\Enums\BackupStatusEnum;
use App\Events\AllDestinationsProcessedEvent;
use App\Events\BackupDestinationCompletedEvent;
use App\Events\BackupDestinationFailedEvent;
use App\Events\BackupDestinationRetryEvent;
use App\Events\BackupDestinationStartedEvent;
use App\Models\BackupLog;
use App\Services\BackupDestinationService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StoreBackupToDestinationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // public int $tries = 1; // We handle retries manually
    public int $timeout = 3600; // 1 hour timeout

    public function __construct(
        public string $backupLogId,
        public string $destinationId,
        public BackupFileDTO $fileData,
        public array $metadata = [],
        public int $retryCount = 0
    ) {}

    public function handle(BackupDestinationService $backupDestinationService): void
    {
        $backupLog = BackupLog::findOrFail($this->backupLogId);
        $this->updateDestinationTimeline($backupLog, [...$this->metadata, ...[
            'retries' => $this->retryCount,
        ]]);

        // Record that we're starting this destination
        BackupDestinationStartedEvent::dispatch(
            $backupLog,
            $this->destinationId,
            $this->fileData,
            $this->metadata
        );

        // Update the existing timeline entry for this destination to show we're processing
        $this->updateDestinationTimeline($backupLog, [
            'has_started' => true,
            'started_at' => now()->toISOString(),
            'retries' => $this->retryCount,
            'retry_scheduled' => false, // Clear the retry scheduled flag when job actually starts
        ]);

        try {
            // Get the destination instance
            $destinations = $backupDestinationService->getEnabledDestinations($backupLog);
            $destination = collect($destinations)->first(function (BackupDestinationInterface $dest) {
                return $dest->getDestinationId() === $this->destinationId;
            });

            if (! $destination) {
                throw new Exception("Destination {$this->destinationId} not found or not enabled");
            }

            // Check if temporary file still exists
            if (! file_exists($this->fileData->fullPath)) {
                throw new Exception("Temporary backup file no longer exists: {$this->fileData->fullPath}");
            }

            // Store the backup to this destination
            $filePath = $destination->store(
                backupLog: $backupLog,
                temporaryFilePath: $this->fileData->fullPath,
                filename: $this->fileData->filename,
                metadata: $this->metadata
            );

            if (! $filePath) {
                throw new Exception('Destination returned null file path');
            }

            // Update timeline with success
            $this->updateDestinationTimeline($backupLog, [
                'success' => true,
                'file_path' => $filePath,
                'completed_at' => now()->toISOString(),
            ]);

            // Emit success event
            BackupDestinationCompletedEvent::dispatch(
                $backupLog,
                $this->destinationId,
                true,
                $filePath
            );

            logger()->debug('Backup successfully stored to destination', [
                'backup_log_id' => $this->backupLogId,
                'destination_id' => $this->destinationId,
                'file_path' => $filePath,
                'retry_count' => $this->retryCount,
            ]);

        } catch (Exception $e) {
            logger()->error('Failed to store backup to destination', [
                'backup_log_id' => $this->backupLogId,
                'destination_id' => $this->destinationId,
                'retry_count' => $this->retryCount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $maxRetries = 3;
            $willRetry = $this->retryCount < $maxRetries - 1;

            // Update timeline with failure
            $this->updateDestinationTimeline($backupLog, [
                'success' => false,
                'error_message' => $e->getMessage(),
                'failed_at' => now()->toISOString(),
                'retries' => $this->retryCount,
                'will_retry' => $willRetry,
            ]);

            if ($willRetry) {
                // Update timeline to indicate retry is scheduled
                $this->updateDestinationTimeline($backupLog, [
                    'retry_scheduled' => true,
                    'retry_scheduled_at' => now()->toISOString(),
                    'delay_seconds' => pow(2, $this->retryCount) * (app()->environment() === 'production' ? 60 : 1),
                ]);

                // Dispatch retry event and job
                BackupDestinationRetryEvent::dispatch(
                    $backupLog,
                    $this->destinationId,
                    $e->getMessage(),
                    $this->retryCount + 1,
                    $this->fileData,
                    $this->metadata
                );

                // Schedule retry with exponential backoff
                $multiplier = app()->environment() === 'production' ? 60 : 1; // multiply by 60s.
                $delay = pow(2, $this->retryCount) * $multiplier; // 1min, 2min, 4min on prod, or 1s, 2s, 4s on local

                // Dispatch the job for this destination
                StoreBackupToDestinationJob::dispatch(
                    $backupLog->id,
                    $this->destinationId,
                    $this->fileData,
                    $this->metadata,
                    $this->retryCount + 1
                )->delay(now()->addSeconds($delay));

            } else {
                // Update backup data
                $this->updateDestinationTimeline($backupLog, [
                    'success' => false,
                    'error_message' => $e->getMessage(),
                    'failed_at' => now()->toISOString(),
                    'retries' => $this->retryCount,
                    'will_retry' => false,
                    'retry_scheduled' => false,
                ]);

                // Final failure - no more retries
                BackupDestinationFailedEvent::dispatch(
                    $backupLog,
                    $this->destinationId,
                    $e,
                    $this->retryCount,
                    false
                );

                // Emit failure event
                BackupDestinationCompletedEvent::dispatch(
                    $backupLog,
                    $this->destinationId,
                    false,
                    null,
                    $e->getMessage()
                );
            }
        } finally {
            // Check if all destinations have been processed (success or final failure)
            $this->checkIfAllDestinationsProcessed($backupLog);
        }
    }

    private function checkIfAllDestinationsProcessed(BackupLog $backupLog): void
    {
        // Get the list of all intended destinations for this backup
        $backupReadyTimeline = $backupLog->timelines()
            ->where('status', 'Backup Ready')
            ->whereNotNull('metadata->destinations_list')
            ->first();

        if (! $backupReadyTimeline || ! isset($backupReadyTimeline->metadata['destinations_list'])) {
            logger()->warning('No destinations list found in backup timeline', [
                'backup_log_id' => $backupLog->id,
            ]);

            return;
        }

        $intendedDestinations = $backupReadyTimeline->metadata['destinations_list'];

        // Get all destination timelines for this backup
        $destinationTimelines = $backupLog->timelines()
            ->where('status', BackupStatusEnum::storing_to_destinations)
            ->get()
            ->groupBy(function ($timeline) {
                return $timeline->metadata['destination_id'] ?? null;
            })
            ->filter(function ($timelines, $destinationId) {
                return ! empty($destinationId);
            });

        // Check if we have timelines for all intended destinations
        $processedDestinations = $destinationTimelines->keys()->toArray();
        $missingDestinations = array_diff(array_values($intendedDestinations), $processedDestinations);

        if (! empty($missingDestinations)) {
            // Some destinations haven't even started yet
            logger()->debug('Some destinations not yet started', [
                'backup_log_id' => $backupLog->id,
                'missing_destinations' => $missingDestinations,
            ]);

            return;
        }

        $allDestinationsProcessed = true;
        $results = [];

        foreach ($destinationTimelines as $destinationId => $timelines) {
            $latestTimeline = $timelines->sortByDesc('created_at')->first();
            $metadata = $latestTimeline->metadata;

            if (isset($metadata['success']) && $metadata['success']) {
                // This destination has a final result
                $results[$destinationId] = [
                    'success' => $metadata['success'],
                    'file_path' => $metadata['file_path'] ?? null,
                    'error' => $metadata['error_message'] ?? null,
                ];
            } elseif (isset($metadata['will_retry']) && $metadata['will_retry']) {
                // This destination will retry, so not all are processed yet
                $allDestinationsProcessed = false;
                break;
            } elseif (isset($metadata['retry_scheduled']) && $metadata['retry_scheduled']) {
                // A retry job is scheduled for this destination, so not all are processed yet
                $allDestinationsProcessed = false;
                break;
            } elseif (isset($metadata['has_started']) && $metadata['has_started'] === false) {
                // This destination hasn't started yet
                $allDestinationsProcessed = false;
                break;
            } elseif (isset($metadata['has_started']) && $metadata['has_started'] === true && ! isset($metadata['success'])) {
                // This destination is currently running (started but no final result yet)
                $allDestinationsProcessed = false;
                break;
            } elseif (
                (isset($metadata['success']) && $metadata['success'] === false) &&
                (isset($metadata['will_retry']) && $metadata['will_retry'] === false) &&
                (! isset($metadata['retry_scheduled']) || $metadata['retry_scheduled'] === false)
            ) {
                // This destination has a final result (failed with no retries and no scheduled retries)
                $results[$destinationId] = [
                    'success' => $metadata['success'],
                    'file_path' => $metadata['file_path'] ?? null,
                    'error' => $metadata['error_message'] ?? null,
                ];
            }
        }

        if ($allDestinationsProcessed && ! empty($results)) {
            logger()->debug('All destinations processed');
            // All destinations have been processed, dispatch final event
            AllDestinationsProcessedEvent::dispatch(
                $backupLog,
                $this->fileData,
                $results
            );
        }
    }

    /**
     * Update the timeline entry for this destination with new metadata.
     *
     * @param  BackupLog  $backupLog
     * @param  array  $newMetadata
     * @return void
     */
    private function updateDestinationTimeline(BackupLog $backupLog, array $newMetadata): void
    {
        // Find the existing timeline entry for this destination
        $timeline = $backupLog->timelines()
            ->where('status', BackupStatusEnum::storing_to_destinations)
            ->whereJsonContains('metadata->destination_id', $this->destinationId)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($timeline) {
            // Merge new metadata with existing metadata
            $existingMetadata = $timeline->metadata ?? [];
            $updatedMetadata = array_merge($existingMetadata, $newMetadata);

            // Update the timeline entry
            $timeline->update([
                'metadata' => $updatedMetadata,
                'updated_at' => now(),
            ]);
        } else {
            logger()->warning('Timeline entry not found for destination', [
                'backup_log_id' => $backupLog->id,
                'destination_id' => $this->destinationId,
            ]);
        }
    }
}
