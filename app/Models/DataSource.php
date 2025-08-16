<?php

namespace App\Models;

use App\Models\Traits\HasUlid32;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function backupLogs(): HasMany
    {
        return $this->hasMany(BackupLog::class);
    }

    public function latestBackupLog(): ?BackupLog
    {
        return $this->backupLogs()->latest('completed_at')->first();
    }

    public function hasHealthyBackup(): bool
    {
        $latestLog = $this->latestBackupLog();

        return $latestLog && $latestLog->isHealthy();
    }

    public function isBackupHealthy(): bool
    {
        return $this->backupLogs()
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subHours(24))
            ->exists();
    }
}
