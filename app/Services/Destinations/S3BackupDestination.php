<?php

namespace App\Services\Destinations;

use App\Contracts\BackupDestinationInterface;
use App\Models\BackupLog;
use Exception;
use Illuminate\Support\Facades\Storage;

class S3BackupDestination implements BackupDestinationInterface
{
    private string $disk;
    private string $path;

    public function __construct()
    {
        $this->disk = env('BACKUP_S3_DISK', 's3');
        $this->path = trim(env('BACKUP_S3_PATH', 'database-backups'), '/');
    }

    public function store(BackupLog $backupLog, string $temporaryFilePath, string $filename, array $metadata = []): ?string
    {
        try {
            $finalPath = $this->path.'/'.$filename;
            $contents = file_get_contents($temporaryFilePath);

            if (! Storage::disk($this->disk)->put($finalPath, $contents)) {
                throw new Exception("Failed to store backup to S3 disk '{$this->disk}'");
            }

            return $finalPath;
        } catch (Exception $e) {
            throw new Exception('S3 destination failed: '.$e->getMessage());
        }
    }

    public function getDestinationId(): string
    {
        return "s3_{$this->disk}";
    }

    public function isEnabled(BackupLog $backupLog): bool
    {
        // Check if S3 disk is configured and accessible
        try {
            $disk = Storage::disk($this->disk);
            // Try to check if the disk is accessible (this will fail if misconfigured)
            $disk->exists('.'); // This should not throw if properly configured

            return true;
        } catch (Exception) {
            return false;
        }
    }
}
