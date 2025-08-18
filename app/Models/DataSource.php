<?php

namespace App\Models;

use App\Models\Traits\HasUlid32;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DataSource extends Model
{
    use HasUlid32;

    protected $fillable = [
        'name',
        'host',
        'port',
        'database',
        'username',
        'password',
        'is_active',
        'skipped_tables',
        'structure_only',
    ];
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'password' => 'encrypted',
            'skipped_tables' => 'array',
            'structure_only' => 'array',
        ];
    }

    /**
     * @return HasMany
     */
    public function backupLogs(): HasMany
    {
        return $this->hasMany(BackupLog::class);
    }

    /**
     * @return BelongsToMany
     */
    public function schedules(): BelongsToMany
    {
        return $this->belongsToMany(Schedule::class, 'schedule_data_sources')
            ->withTimestamps();
    }

    /**
     * @return HasOne
     */
    public function latestBackupLog(): HasOne
    {
        return $this->hasOne(BackupLog::class)->latest()->limit(1);
    }

    /**
     * @return bool
     */
    public function hasHealthyBackup(): bool
    {
        $latestLog = $this->latestBackupLog;

        return $latestLog && $latestLog->isHealthy();
    }

    /**
     * @return bool
     */
    public function isBackupHealthy(): bool
    {
        return $this->hasHealthyBackup();
    }
}
