<?php

namespace App\Contracts;

use App\DTO\ConnectionDTO;
use App\Models\BackupLog;

interface BackupServiceInterface
{
    /**
     * Execute a database backup for the given connection.
     *
     * @param  ConnectionDTO  $connection  The database connection parameters
     *
     * @throws \App\Exceptions\BackupException
     *
     * @return \App\DTO\BackupFileDTO The path to the created backup file
     */
    public function backup(ConnectionDTO $connection, BackupLog $backupLog): \App\DTO\BackupFileDTO;

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
}
