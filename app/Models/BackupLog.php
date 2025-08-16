<?php

namespace App\Models;

use App\Enums\BackupStatusEnum;
use App\Enums\BackupTypeEnum;
use App\Models\Traits\HasUlid32;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupLog extends Model
{
    use HasUlid32;

    protected $fillable = [
        'data_source_id',
        'status',
        'type',
        'disk',
        'filename',
        'file_path',
        'file_size',
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
            'file_size' => 'integer',
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
     * @return void
     */
    public function markAsRunning(): void
    {
        $this->update([
            'status' => BackupStatusEnum::running,
            'started_at' => now(),
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
    }

    /**
     * @param  string  $errorMessage
     * @return void
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => BackupStatusEnum::failed,
            'error_message' => $errorMessage,
            'completed_at' => now(),
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
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
