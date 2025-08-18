<?php

namespace App\Models;

use App\Models\Traits\HasUlid32;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    use HasUlid32;

    protected $fillable = [
        'name',
        'description',
        'hour',
        'days_of_week',
        'is_active',
        'last_run_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'is_active' => 'boolean',
            'hour' => 'integer',
            'last_run_at' => 'datetime',
        ];
    }

    protected $appends = [
        'human_days',
        'human_time',
    ];

    /**
     * Get the data sources that belong to this schedule.
     *
     * @return BelongsToMany
     */
    public function dataSources(): BelongsToMany
    {
        return $this->belongsToMany(DataSource::class, 'schedule_data_sources')
            ->withTimestamps();
    }

    /**
     * Get the backup logs that were created by this schedule.
     *
     * @return HasMany
     */
    public function backupLogs(): HasMany
    {
        return $this->hasMany(BackupLog::class);
    }

    /**
     * Check if the schedule should run today (in UTC).
     *
     * @return bool
     */
    public function shouldRunToday(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $today = now()->dayOfWeek; // 0 = Sunday, 6 = Saturday

        // Convert to our format (1 = Monday, 7 = Sunday)
        $todayAdjusted = $today === 0 ? 7 : $today;

        return in_array($todayAdjusted, $this->days_of_week);
    }

    /**
     * Check if the schedule should run at the current hour (in UTC).
     *
     * @return bool
     */
    public function shouldRunNow(): bool
    {
        if (! $this->shouldRunToday()) {
            return false;
        }

        $currentHour = now()->hour;

        return $currentHour === $this->hour;
    }

    /**
     * Get human-readable days of week.
     *
     * @return string
     */
    public function getHumanDaysAttribute(): string
    {
        $days = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];

        if (count($this->days_of_week) === 7) {
            return 'Every day';
        }

        if (count($this->days_of_week) === 5 &&
            array_diff([1, 2, 3, 4, 5], $this->days_of_week) === []) {
            return 'Weekdays';
        }

        if (count($this->days_of_week) === 2 &&
            array_diff([6, 7], $this->days_of_week) === []) {
            return 'Weekends';
        }

        $selectedDays = array_map(fn($day) => $days[$day], $this->days_of_week);

        return implode(', ', $selectedDays);
    }

    /**
     * Get human-readable time (in UTC).
     *
     * @return string
     */
    public function getHumanTimeAttribute(): string
    {
        return sprintf('%02d:00 UTC', $this->hour);
    }

    /**
     * Update the last run timestamp.
     *
     * @return void
     */
    public function markAsRun(): void
    {
        $this->update(['last_run_at' => now()]);
    }

    /**
     * Get the next run time for this schedule (in UTC).
     *
     * @return \Carbon\Carbon|null
     */
    public function getNextRunTime(): ?\Carbon\Carbon
    {
        if (! $this->is_active || empty($this->days_of_week)) {
            return null;
        }

        $now = now();

        // Try today first if the hour hasn't passed
        $todayAdjusted = $now->dayOfWeek === 0 ? 7 : $now->dayOfWeek;
        if (in_array($todayAdjusted, $this->days_of_week) && $now->hour < $this->hour) {
            return $now->copy()->hour($this->hour)->minute(0)->second(0);
        }

        // Find the next day this schedule should run
        for ($i = 1; $i <= 7; $i++) {
            $checkDate = $now->copy()->addDays($i);
            $checkDay = $checkDate->dayOfWeek === 0 ? 7 : $checkDate->dayOfWeek;

            if (in_array($checkDay, $this->days_of_week)) {
                return $checkDate->hour($this->hour)->minute(0)->second(0);
            }
        }

        return null;
    }

    /**
     * Get count of associated data sources.
     *
     * @return int
     */
    public function getDataSourcesCountAttribute(): int
    {
        return $this->dataSources()->count();
    }

    /**
     * Get the most recent backup log.
     *
     * @return BackupLog|null
     */
    public function getLatestBackupLogAttribute(): ?BackupLog
    {
        return $this->backupLogs()->latest()->first();
    }
}
