<?php

namespace App\Models;

use App\Enums\BackupStatusEnum;
use App\Enums\BackupTypeEnum;
use App\Models\Traits\HasUlid32;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Throwable;

class BackupLog extends Model
{
    use HasUlid32;

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
        'status',
        'type',
        'disk',
        'filename',
        'file_path',
        'file_size',
        'file_deleted_at',
        'warnings',
        'errors',
        'metadata',
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
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'file_deleted_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    protected $appends = [
        'human_size',
    ];

    /**
     * @return BelongsTo
     */
    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
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
     * @param  string  $filename
     * @param  string  $filePath
     * @param  int  $fileSize
     * @param  array|null  $metadata
     * @return void
     */
    public function markAsCompleted(string $filename, string $filePath, int $fileSize, ?array $metadata = null): void
    {
        $this->update([
            'status' => BackupStatusEnum::completed,
            'filename' => $filename,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'metadata' => $metadata,
            'completed_at' => now(),
        ]);

        $this->recordStatusChange(BackupStatusEnum::completed, [
            'completed_at' => now()->toISOString(),
            'filename' => $filename,
            'file_path' => $filePath,
            'file_size' => $fileSize,
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
     * Get human-readable file size.
     */
    public function getHumanSizeAttribute(): string
    {
        if (! $this->file_size) {
            return 'Unknown';
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        for ($i = 0; $bytes > 1000; $i++) {
            $bytes /= 1000;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Check if the backup file is available (not deleted).
     */
    public function isFileAvailable(): bool
    {
        return ! $this->file_deleted_at && $this->file_path && $this->status === BackupStatusEnum::completed;
    }

    /**
     * Mark the backup file as deleted.
     */
    public function markFileAsDeleted(): void
    {
        $this->update([
            'file_deleted_at' => now(),
        ]);
    }

    /**
     * Get duration between started_at and completed_at.
     */
    public function getDuration(): ?int
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        return $this->completed_at->diffInSeconds($this->started_at);
    }

    /**
     * Get human-readable duration.
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
}
