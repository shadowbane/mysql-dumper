<?php

namespace App\Services\Destinations;

use App\Contracts\BackupDestinationInterface;
use App\Models\BackupLog;
use App\Models\File;
use Exception;
use Illuminate\Support\Facades\Storage;

class S3BackupDestination implements BackupDestinationInterface
{
    public function __construct(
        private readonly string $disk,
        private readonly string $path,
    ) {}

    /**
     * Store the backup file to this destination.
     *
     * @param  BackupLog  $backupLog
     * @param  string  $temporaryFilePath
     * @param  string  $filename
     * @param  array  $metadata
     *
     * @throws Exception
     *
     * @return string|null The final path where the file was stored, or null if failed
     */
    public function store(
        BackupLog $backupLog,
        string $temporaryFilePath,
        string $filename,
        array $metadata = []
    ): ?string {
        try {
            $finalPath = $this->path.'/'.$filename;
            $contents = file_get_contents($temporaryFilePath);

            if (! Storage::disk($this->disk)->put($finalPath, $contents)) {
                throw new Exception("Failed to store backup to '{$this->disk}' disk");
            }

            // Store record on file
            $this->createFileRecord(
                backupLog: $backupLog,
                filename: $filename,
                path: $finalPath,
                sizeBytes: filesize($temporaryFilePath),
                hash: hash('md5', $contents),
            );

            // Free up memory by unsetting $contents
            unset($contents);

            return $finalPath;
        } catch (Exception $e) {
            logger()->error("{$this->disk} destinaton failed: {$e->getMessage()}", [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'previous' => $e->getPrevious(),
            ]);

            if (Storage::disk($this->disk)->exists($finalPath)) {
                Storage::disk($this->disk)->delete($finalPath);
            }

            throw new Exception("{$this->disk} destination failed: {$e->getMessage()}");
        }
    }

    public function getDestinationId(): string
    {
        return "s3_{$this->disk}";
    }

    /**
     * Create a file record for this destination.
     *
     * @param  BackupLog  $backupLog
     * @param  string  $filename
     * @param  string  $path
     * @param  int  $sizeBytes
     * @param  string  $hash
     * @param  array  $metadata
     *
     * @throws Exception
     *
     * @return File|null
     */
    public function createFileRecord(BackupLog $backupLog, string $filename, string $path, int $sizeBytes, string $hash, array $metadata = []): ?File
    {
        return $backupLog->files()->create([
            'filename' => $filename,
            'path' => $path,
            'disk' => $this->disk,
            'size_bytes' => $sizeBytes,
            'mime_type' => 'application/zip',
            'is_public' => false,
            'label' => $this->getDestinationId(),
            'hash' => $hash,
        ]);
    }

    /**
     * Delete a file record.
     *
     * @param  File  $file
     * @return bool
     */
    public function deleteFileRecord(File $file): bool
    {
        if (Storage::disk($file->disk)->exists($file->path)) {
            Storage::disk($file->disk)->delete($file->path);
        }

        return $file->delete();
    }

    /**
     * Serve a file from storage for the user to download.
     *
     * @param  File  $file
     * @return string
     */
    public function download(File $file): string
    {
        // Check if the file exists on the specified disk.
        if (! Storage::disk($this->disk)->exists($file->path)) {
            // If not, abort the request with a 404 'Not Found' error.
            abort(404, 'File not found.');
        }

        return Storage::disk($this->disk)
            ->temporaryUrl($file->path, now()->addHours(3));
    }

    public function isEnabled(BackupLog $backupLog): bool
    {
        // Check if S3 disk is configured and accessible
        try {
            $disk = Storage::disk($this->disk);
            // Try to check if the disk is accessible (this will fail if misconfigured)
            $disk->allFiles(''); // This should not throw if properly configured

            return true;
        } catch (Exception) {
            return false;
        }
    }
}
