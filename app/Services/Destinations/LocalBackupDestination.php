<?php

namespace App\Services\Destinations;

use App\Contracts\BackupDestinationInterface;
use App\Models\BackupLog;
use Exception;
use Illuminate\Support\Facades\Storage;

class LocalBackupDestination implements BackupDestinationInterface
{
    private string $disk;
    private string $path;

    public function __construct()
    {
        $this->disk = env('BACKUP_LOCAL_DISK', 'local');
        $this->path = trim(env('BACKUP_LOCAL_PATH', 'database-backups'), '/');
    }

    public function store(BackupLog $backupLog, string $temporaryFilePath, string $filename, array $metadata = []): ?string
    {
        try {
            $finalPath = $this->path.'/'.$filename;
            $contents = file_get_contents($temporaryFilePath);

            if (! Storage::disk($this->disk)->put($finalPath, $contents)) {
                throw new Exception("Failed to store backup to local disk '{$this->disk}'");
            }

            return $finalPath;
        } catch (Exception $e) {
            throw new Exception('Local destination failed: '.$e->getMessage());
        }
    }

    public function getDestinationId(): string
    {
        return "local_{$this->disk}";
    }

    public function isEnabled(BackupLog $backupLog): bool
    {
        // Always enabled if the disk exists and is configured
        try {
            return Storage::disk($this->disk) !== null;
        } catch (Exception) {
            return false;
        }
    }
}
