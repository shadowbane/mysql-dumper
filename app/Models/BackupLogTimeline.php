<?php

namespace App\Models;

use App\Enums\BackupStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupLogTimeline extends Model
{
    protected $fillable = [
        'backup_log_id',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => BackupStatusEnum::class,
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo
     */
    public function backupLog(): BelongsTo
    {
        return $this->belongsTo(BackupLog::class);
    }

    /**
     * Get the duration since the previous timeline entry.
     *
     * @return int|null
     */
    public function getDurationFromPrevious(): ?int
    {
        $previous = self::where('backup_log_id', $this->backup_log_id)
            ->where('created_at', '<', $this->created_at)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $previous) {
            return null;
        }

        return $this->created_at->diffInSeconds($previous->created_at);
    }

    /**
     * Get human-readable duration from previous status.
     *
     * @return string|null
     */
    public function getHumanDurationFromPrevious(): ?string
    {
        $seconds = $this->getDurationFromPrevious();

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
     * Check if this timeline entry is for a specific destination.
     *
     * @param  string  $destinationId
     * @return bool
     */
    public function isForDestination(string $destinationId): bool
    {
        return isset($this->metadata['destination_id']) &&
               $this->metadata['destination_id'] === $destinationId;
    }

    /**
     * Get destination ID from metadata if available.
     *
     * @return string|null
     */
    public function getDestinationId(): ?string
    {
        return $this->metadata['destination_id'] ?? null;
    }

    /**
     * Check if this is a destination-specific timeline entry.
     *
     * @return bool
     */
    public function isDestinationSpecific(): bool
    {
        return $this->status->value === BackupStatusEnum::storing_to_destinations &&
               isset($this->metadata['destination_id']);
    }

    /**
     * Check if this destination attempt was successful.
     *
     * @return bool|null
     */
    public function isDestinationSuccessful(): ?bool
    {
        if (! $this->isDestinationSpecific()) {
            return null;
        }

        return $this->metadata['success'] ?? null;
    }

    /**
     * Check if this destination will retry.
     *
     * @return bool
     */
    public function willRetry(): bool
    {
        return $this->metadata['will_retry'] ?? false;
    }

    /**
     * Get the retry count for this attempt.
     *
     * @return int
     */
    public function getRetryCount(): int
    {
        return $this->metadata['retries'] ?? 0;
    }

    /**
     * Scope to filter by destination ID.
     */
    public function scopeForDestination($query, string $destinationId)
    {
        return $query->whereJsonContains('metadata->destination_id', $destinationId);
    }

    /**
     * Scope to filter destination-specific entries.
     */
    public function scopeDestinationSpecific($query)
    {
        return $query->where('status', BackupStatusEnum::storing_to_destinations)
            ->whereNotNull('metadata->destination_id');
    }
}
