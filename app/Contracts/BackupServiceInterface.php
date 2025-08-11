<?php

namespace App\Contracts;

use App\DTO\ConnectionDTO;

interface BackupServiceInterface
{
    /**
     * Execute a database backup for the given connection.
     *
     * @param  ConnectionDTO  $connection  The database connection parameters
     * @param  string|null  $filename  Custom filename for the backup (optional)
     *
     * @throws \App\Exceptions\BackupException
     *
     * @return string The path to the created backup file
     */
    public function backup(ConnectionDTO $connection, ?string $filename = null): string;

    /**
     * Test the database connection before attempting backup.
     *
     * @param  ConnectionDTO  $connection  The database connection parameters
     *
     * @throws \App\Exceptions\BackupException
     *
     * @return bool True if connection is successful
     */
    public function testConnection(ConnectionDTO $connection): bool;

    /**
     * Get the estimated size of the database before backup.
     *
     * @param  ConnectionDTO  $connection  The database connection parameters
     *
     * @throws \App\Exceptions\BackupException
     *
     * @return int Size in bytes
     */
    public function getDatabaseSize(ConnectionDTO $connection): int;

    /**
     * Get a list of tables in the database.
     *
     * @param  ConnectionDTO  $connection  The database connection parameters
     *
     * @throws \App\Exceptions\BackupException
     *
     * @return array List of table names
     */
    public function getTables(ConnectionDTO $connection): array;

    /**
     * Set the disk where backups should be stored.
     *
     * @param  string  $disk  The filesystem disk name
     * @return self
     */
    public function setDisk(string $disk): self;

    /**
     * Set the directory path within the disk where backups should be stored.
     *
     * @param  string  $path  The directory path
     * @return self
     */
    public function setPath(string $path): self;
}
