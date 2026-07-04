<?php

namespace App\Services\Destinations;

use App\Contracts\BackupDestinationInterface;
use App\Models\BackupLog;
use App\Models\File;
use Exception;
use Illuminate\Support\Facades\Storage;

class SftpBackupDestination implements BackupDestinationInterface
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
        $finalPath = $this->path.'/'.$filename;

        try {
            $stream = fopen($temporaryFilePath, 'rb');

            if ($stream === false) {
                throw new Exception("Unable to open temporary backup file '{$temporaryFilePath}' for reading");
            }

            try {
                if (! Storage::disk($this->disk)->writeStream($finalPath, $stream)) {
                    throw new Exception("Failed to store backup to '{$this->disk}' SFTP disk");
                }
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            // Verify what actually landed — SFTP connections dropping midway
            // are the classic source of silently truncated uploads.
            $expectedSize = filesize($temporaryFilePath);
            $storedSize = Storage::disk($this->disk)->size($finalPath);

            if ($storedSize !== $expectedSize) {
                throw new Exception("Stored file size mismatch on '{$this->disk}' SFTP disk: expected {$expectedSize} bytes, got {$storedSize}");
            }

            // Store record on file
            $this->createFileRecord(
                backupLog: $backupLog,
                filename: $filename,
                path: $finalPath,
                sizeBytes: filesize($temporaryFilePath),
                hash: hash_file('md5', $temporaryFilePath),
            );

            return $finalPath;
        } catch (Exception $e) {
            logger()->error("{$this->disk} destinaton failed: {$e->getMessage()}", [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'previous' => $e->getPrevious(),
            ]);

            // Best-effort cleanup of a partial upload; if the SFTP endpoint is
            // still unreachable this would throw and mask the original error.
            try {
                if (Storage::disk($this->disk)->exists($finalPath)) {
                    Storage::disk($this->disk)->delete($finalPath);
                }
            } catch (Exception $cleanupException) {
                logger()->warning("Could not clean up partial upload on '{$this->disk}': {$cleanupException->getMessage()}");
            }

            throw new Exception("SFTP {$this->disk} destination failed: {$e->getMessage()}");
        }
    }

    public function getDestinationId(): string
    {
        return "sftp_{$this->disk}";
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
     * @return null
     */
    public function download(File $file): null
    {
        return null;
    }

    public function isEnabled(BackupLog $backupLog): bool
    {
        // Check if SFTP disk is configured and accessible
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
