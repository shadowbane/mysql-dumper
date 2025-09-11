<?php

namespace App\Models;

use App\Enums\BackupStatusEnum;
use App\Enums\BackupTypeEnum;
use App\Models\Traits\HasFiles;
use App\Models\Traits\HasUlid32;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Throwable;

class BackupLog extends Model
{
    use HasFiles, HasUlid32;

    protected static function boot(): void
    {
        parent::boot();

        static::created(function ($backupLog) {
            // Create initial timeline entry when backup log is created
            $backupLog->recordStatusChange($backupLog->status, [
                'created_at' => $backupLog->created_at->toISOString(),
                'type' => $backupLog->type->value,
                'data_source_id' => $backupLog->data_source_id,
            ]);
        });
    }

    protected $fillable = [
        'data_source_id',
        'schedule_id',
        'status',
        'type',
        'warnings',
        'errors',
        'metadata',
        'locked',
        'started_at',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BackupStatusEnum::class,
            'type' => BackupTypeEnum::class,
            'warnings' => 'array',
            'errors' => 'array',
            'metadata' => 'array',
            'locked' => 'bool',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo
     */
    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }

    /**
     * @return BelongsTo
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * @return HasMany
     */
    public function timelines(): HasMany
    {
        return $this->hasMany(BackupLogTimeline::class)->orderBy('created_at');
    }

    /**
     * Create timeline entry for status change.
     *
     * @param  BackupStatusEnum  $status
     * @param  array  $metadata
     * @return void
     */
    public function recordStatusChange(BackupStatusEnum $status, array $metadata = []): void
    {
        $this->timelines()->create([
            'status' => $status,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @return void
     */
    public function markAsRunning(): void
    {
        $this->update([
            'status' => BackupStatusEnum::running,
            'started_at' => now(),
        ]);

        $this->recordStatusChange(BackupStatusEnum::running, [
            'started_at' => now()->toISOString(),
        ]);
    }

    /**
     * @param  array|null  $metadata
     * @return void
     */
    public function markAsCompleted(?array $metadata = null): void
    {
        $this->update([
            'status' => BackupStatusEnum::completed,
            'metadata' => $metadata,
            'completed_at' => now(),
        ]);

        $this->recordStatusChange(BackupStatusEnum::completed, [
            'completed_at' => now()->toISOString(),
            'backup_metadata' => $metadata,
        ]);
    }

    /**
     * @param  string|array  $warning
     * @return void
     */
    public function addWarning(string|array $warning): void
    {
        $warnings = $this->warnings ?? [];
        $warnings[] = is_string($warning) ? ['message' => $warning] : $warning;
        $this->update(['warnings' => $warnings]);
    }

    /**
     * Mark the backup as ready for destination storage.
     *
     * @param  array|null  $metadata
     * @return void
     */
    public function markAsBackupReady(?array $metadata = null): void
    {
        $this->update([
            'status' => BackupStatusEnum::backup_ready,
            'metadata' => $metadata,
        ]);

        $this->recordStatusChange(BackupStatusEnum::backup_ready, [
            'backup_ready_at' => now()->toISOString(),
            'backup_metadata' => $metadata,
        ]);
    }

    /**
     * Mark the backup as storing to destinations.
     *
     * @param  array  $destinations
     * @return void
     */
    public function markAsStoringToDestinations(array $destinations = []): void
    {
        $this->update([
            'status' => BackupStatusEnum::storing_to_destinations,
        ]);

        $this->recordStatusChange(BackupStatusEnum::storing_to_destinations, [
            'storing_started_at' => now()->toISOString(),
            'destinations' => $destinations,
        ]);
    }

    /**
     * Record a destination completion (success or failure).
     *
     * @param  string  $destinationId
     * @param  bool  $success
     * @param  string|null  $filePath
     * @param  string|null  $errorMessage
     * @param  array  $metadata
     * @return void
     */
    public function recordDestinationCompletion(
        string $destinationId,
        bool $success,
        ?string $filePath = null,
        ?string $errorMessage = null,
        array $metadata = []
    ): void {
        $timelineMetadata = [
            'destination_id' => $destinationId,
            'success' => $success,
            'completed_at' => now()->toISOString(),
        ];

        if ($success && $filePath) {
            $timelineMetadata['file_path'] = $filePath;
        }

        if (! $success && $errorMessage) {
            $timelineMetadata['error_message'] = $errorMessage;
        }

        if (! empty($metadata)) {
            $timelineMetadata['metadata'] = $metadata;
        }

        $this->recordStatusChange(BackupStatusEnum::storing_to_destinations, $timelineMetadata);
    }

    /**
     * Mark the backup as partially failed (some destinations succeeded, others failed).
     *
     * @param  array|null  $metadata
     * @return void
     */
    public function markAsPartiallyFailed(?array $metadata = null): void
    {
        $this->update([
            'status' => BackupStatusEnum::partially_failed,
            'metadata' => $metadata,
            'completed_at' => now(),
        ]);

        $this->recordStatusChange(BackupStatusEnum::partially_failed, [
            'partially_failed_at' => now()->toISOString(),
            'backup_metadata' => $metadata,
        ]);
    }

    /**
     * @param  Throwable  $exception
     * @return void
     */
    public function markAsFailed(Throwable $exception): void
    {
        $errors = $this->errors ?? [];

        $errorDetails = [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        if ($exception instanceof \App\Exceptions\BackupException) {
            $errorDetails['context'] = $exception->getContext();
        }

        $errors[] = $errorDetails;

        $this->update([
            'status' => BackupStatusEnum::failed,
            'errors' => $errors,
            'completed_at' => now(),
        ]);

        $this->recordStatusChange(BackupStatusEnum::failed, [
            'failed_at' => now()->toISOString(),
            'error_message' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
            'error_file' => $exception->getFile(),
            'error_line' => $exception->getLine(),
        ]);
    }

    /**
     * @return bool
     */
    public function isHealthy(): bool
    {
        return $this->status === BackupStatusEnum::completed &&
               $this->completed_at &&
               $this->completed_at->isAfter(now()->subHours(24));
    }

    /**
     * Get human-readable total file size for all backup files.
     *
     * @return string
     */
    public function getHumanSizeAttribute(): string
    {
        // Use max, to determine max file size
        // This is useful because we need to know a single backup file size,
        // instead of the sum of all backup across storages.
        $totalBytes = $this->files()->max('size_bytes');

        if (! $totalBytes) {
            return 'Unknown';
        }

        $bytes = $totalBytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        for ($i = 0; $bytes > 1000; $i++) {
            $bytes /= 1000;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Check if backup files are available (not deleted).
     *
     * @return bool
     */
    public function isFileAvailable(): bool
    {
        return $this->files()->exists() &&
               in_array($this->status, [BackupStatusEnum::completed, BackupStatusEnum::partially_failed]);
    }

    /**
     * Mark all backup files as deleted.
     *
     * @return void
     */
    public function markFilesAsDeleted(): void
    {
        $this->files()->update([
            'deleted_at' => now(),
        ]);
    }

    /**
     * Get duration between started_at and completed_at.
     *
     * @return int|null
     */
    public function getDuration(): ?int
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        $completeTime = $this->timelines
            ->whereIn('status', [BackupStatusEnum::completed, BackupStatusEnum::partially_failed])
            ->first();

        if (blank($completeTime)) {
            return null;
        }

        return $this->created_at->diffInSeconds($completeTime->created_at, true);
    }

    /**
     * Get human-readable duration.
     *
     * @return string|null
     */
    public function getHumanDuration(): ?string
    {
        $seconds = $this->getDuration();

        if ($seconds === null) {
            return null;
        }

        if ($seconds < 60) {
            return "{$seconds}s";
        } elseif ($seconds < 3600) {
            return round($seconds / 60, 1).'m';
        }

        return round($seconds / 3600, 1).'h';

    }

    /**
     * Get destination status summary from timelines.
     *
     * @return array
     */
    public function getDestinationStatusSummary(): array
    {
        $destinationTimelines = $this->timelines()
            ->destinationSpecific()
            ->get()
            ->groupBy(function ($timeline) {
                return $timeline->getDestinationId();
            });

        $summary = [];

        foreach ($destinationTimelines as $destinationId => $timelines) {
            $latestTimeline = $timelines->sortByDesc('created_at')->first();
            $metadata = $latestTimeline->metadata;

            $summary[$destinationId] = [
                'status' => $this->determineDestinationStatus($timelines),
                'retries' => $latestTimeline->getRetryCount(),
                'last_error' => $metadata['error_message'] ?? null,
                'file_path' => $metadata['file_path'] ?? null,
                'success' => $metadata['success'] ?? null,
                'will_retry' => $latestTimeline->willRetry(),
                'latest_timeline' => $latestTimeline,
            ];
        }

        return $summary;
    }

    /**
     * Determine the current status of a destination based on its timelines.
     *
     * @param  \Illuminate\Support\Collection  $timelines
     * @return string
     */
    private function determineDestinationStatus($timelines): string
    {
        $latestTimeline = $timelines->sortByDesc('created_at')->first();
        $metadata = $latestTimeline->metadata;

        if (isset($metadata['success'])) {
            return $metadata['success'] ? 'completed' : 'failed';
        }

        if ($metadata['will_retry'] ?? false) {
            return 'retrying';
        }

        if ($metadata['has_started'] ?? false) {
            return 'processing';
        }

        return 'queued';
    }

    /**
     * Check if all destinations have completed (either success or final failure).
     *
     * @return bool
     */
    public function areAllDestinationsCompleted(): bool
    {
        $summary = $this->getDestinationStatusSummary();

        foreach ($summary as $destinationData) {
            $status = $destinationData['status'];
            if ($status === 'queued' || $status === 'processing' || $status === 'retrying') {
                return false;
            }
        }

        return ! empty($summary); // Only true if there are destinations AND all are completed
    }

    /**
     * Get count of successful/failed destinations.
     *
     * @return array ['successful' => int, 'failed' => int, 'total' => int]
     */
    public function getDestinationCounts(): array
    {
        $summary = $this->getDestinationStatusSummary();
        $successful = 0;
        $failed = 0;

        foreach ($summary as $destinationData) {
            if ($destinationData['status'] === 'completed') {
                $successful++;
            } elseif ($destinationData['status'] === 'failed') {
                $failed++;
            }
        }

        return [
            'successful' => $successful,
            'failed' => $failed,
            'total' => count($summary),
        ];
    }
}
