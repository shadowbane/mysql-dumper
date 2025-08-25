<?php

namespace App\Contracts;

use App\Models\BackupLog;
use App\Models\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface BackupDestinationInterface
{
    /**
     * Store the backup file to this destination.
     *
     * @param  BackupLog  $backupLog
     * @param  string  $temporaryFilePath
     * @param  string  $filename
     * @param  array  $metadata
     * @return string|null The final path where the file was stored, or null if failed
     */
    public function store(BackupLog $backupLog, string $temporaryFilePath, string $filename, array $metadata = []): ?string;

    /**
     * Create a file record for this destination.
     *
     * @param  BackupLog  $backupLog
     * @param  string  $filename
     * @param  string  $path
     * @param  int  $sizeBytes
     * @param  array  $metadata
     * @return File|null
     */
    public function createFileRecord(BackupLog $backupLog, string $filename, string $path, int $sizeBytes, array $metadata = []): ?File;

    /**
     * Delete a file record.
     *
     * @param  File  $file
     * @return bool
     */
    public function deleteFileRecord(File $file): bool;

    /**
     * Serve a file from storage for the user to download.
     *
     * @param  File  $file
     * @return StreamedResponse|string
     */
    public function download(File $file): StreamedResponse|string;

    /**
     * Get the destination identifier (e.g., 's3', 'r2', 'local').
     *
     * @return string
     */
    public function getDestinationId(): string;

    /**
     * Check if this destination is enabled for the given backup log.
     *
     * @param  BackupLog  $backupLog
     * @return bool
     */
    public function isEnabled(BackupLog $backupLog): bool;
}
