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
}
