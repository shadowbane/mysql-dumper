<?php

namespace App\Services;

use App\Contracts\BackupServiceInterface;
use App\DTO\ConnectionDTO;
use App\Exceptions\BackupException;
use App\Models\BackupLog;
use Druidfi\Mysqldump as IMysqldump;
use Druidfi\Mysqldump\Compress\CompressManagerFactory;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use ZipArchive;

class BackupService implements BackupServiceInterface
{
    private string $disk = 'local';
    private string $path = 'database-backups';

    /**
     * @throws Exception
     */
    public function runBackupProcess(BackupLog $backupLog): void
    {
        try {
            // Mark backup as running
            $backupLog->markAsRunning();

            $dataSource = $backupLog->dataSource;

            // Create connection DTO
            $connectionDTO = new ConnectionDTO(
                host: $dataSource->host,
                port: $dataSource->port,
                database: $dataSource->database,
                username: $dataSource->username,
                password: $dataSource->password,
                skippedTables: $dataSource->skipped_tables ? preg_split('/\s*,\s*/', $dataSource->skipped_tables) : [],
                structureOnly: $dataSource->structure_only ? preg_split('/\s*,\s*/', $dataSource->structure_only) : []
            );

            // Set backup service disk and path
            $this->setDisk($backupLog->disk);

            // Perform backup
            $filePath = $this->backup($connectionDTO);

            // Get file info for backup log
            $filename = basename($filePath);
            $fileSize = Storage::disk($backupLog->disk)->size($filePath);

            // Mark backup as completed
            $backupLog->markAsCompleted(
                filename: $filename,
                filePath: $filePath,
                fileSize: $fileSize,
                metadata: [
                    'database' => $dataSource->database,
                    'tables_backed_up' => count($connectionDTO->skippedTables ? array_diff($this->getTables($connectionDTO), $connectionDTO->skippedTables) : $this->getTables($connectionDTO)),
                    'structure_only_tables' => $connectionDTO->structureOnly,
                    'skipped_tables' => $connectionDTO->skippedTables,
                ]
            );

        } catch (Exception $e) {
            // Mark backup as failed
            $backupLog->markAsFailed($e);

            // Re-throw to trigger job failure handling
            throw $e;
        }
    }

    public function backup(ConnectionDTO $connection, ?string $filename = null): string
    {
        $this->validateConnection($connection);

        $temporaryDirectory = TemporaryDirectory::make();

        try {
            // Generate filename if not provided
            if (! $filename) {
                $timestamp = now()->format('Y-m-d_H-i-s');
                $filename = "{$connection->database}_{$timestamp}.zip";
            }

            // Get all tables to backup
            $tables = $this->getTablesToBackup($connection);

            if (empty($tables)) {
                throw BackupException::backupFailed($connection->database, null, [
                    'reason' => 'No tables found to backup',
                ]);
            }

            // Create individual table dumps
            $tableFiles = [];
            foreach ($tables as $table) {
                $tableFile = $this->backupTable($connection, $table, $temporaryDirectory);
                $tableFiles[] = $tableFile;
            }

            // Create database structure dump (no data)
            $structureFile = $this->backupDatabaseStructure($connection, $temporaryDirectory);
            if ($structureFile) {
                $tableFiles[] = $structureFile;
            }

            // Create ZIP archive
            $zipPath = $this->createZipArchive($tableFiles, $temporaryDirectory, $connection->database);

            // Upload to the configured disk and path
            $finalPath = $this->path.'/'.$filename;
            $zipContents = file_get_contents($zipPath);

            if (! Storage::disk($this->disk)->put($finalPath, $zipContents)) {
                throw BackupException::fileWriteFailed($finalPath);
            }

            return $finalPath;

        } catch (Exception $e) {
            if ($e instanceof BackupException) {
                throw $e;
            }

            throw BackupException::backupFailed($connection->database, $e, [
                'reason' => $e->getMessage(),
            ]);
        } finally {
            $temporaryDirectory->delete();
        }
    }

    public function testConnection(ConnectionDTO $connection): bool
    {
        try {
            $this->validateConnection($connection);

            $dsn = "mysql:host={$connection->host};port={$connection->port};dbname={$connection->database}";
            $pdo = new \PDO($dsn, $connection->username, $connection->password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);

            return true;

        } catch (Exception $e) {
            throw BackupException::connectionFailed($connection->host, $connection->port, $e);
        }
    }

    public function getDatabaseSize(ConnectionDTO $connection): int
    {
        try {
            $this->validateConnection($connection);

            $configName = 'temp_connection_'.uniqid();
            Config::set("database.connections.{$configName}", $connection->getDatabaseConfig());

            $result = DB::connection($configName)->select(' 
                SELECT
                    ROUND(SUM(data_length + index_length)) as size_bytes
                FROM information_schema.tables
                WHERE table_schema = ?
            ', [$connection->database]);

            Config::set("database.connections.{$configName}", null);

            return (int) ($result[0]->size_bytes ?? 0);

        } catch (Exception $e) {
            throw BackupException::backupFailed($connection->database, $e, [
                'operation' => 'get_database_size',
                'reason' => $e->getMessage(),
            ]);
        }
    }

    public function getTables(ConnectionDTO $connection): array
    {
        try {
            $this->validateConnection($connection);

            $configName = 'temp_connection_'.uniqid();
            Config::set("database.connections.{$configName}", $connection->getDatabaseConfig());

            $result = DB::connection($configName)->select(' 
                SELECT table_name
                FROM information_schema.tables
                WHERE table_schema = ?
                ORDER BY table_name
            ', [$connection->database]);

            Config::set("database.connections.{$configName}", null);

            return array_map(fn($table) => $table->TABLE_NAME, $result);

        } catch (Exception $e) {
            throw BackupException::backupFailed($connection->database, $e, [
                'operation' => 'get_tables',
                'reason' => $e->getMessage(),
            ]);
        }
    }

    public function setDisk(string $disk): self
    {
        if (! Storage::disk($disk)) {
            throw BackupException::invalidConfiguration('disk', "Disk '{$disk}' is not configured");
        }

        $this->disk = $disk;

        return $this;
    }

    public function setPath(string $path): self
    {
        $this->path = trim($path, '/');

        return $this;
    }

    private function getTablesToBackup(ConnectionDTO $connection): array
    {
        $allTables = $this->getTables($connection);

        if (empty($connection->skippedTables)) {
            return $allTables;
        }

        return array_filter($allTables, function ($table) use ($connection) {
            return ! in_array($table, $connection->skippedTables);
        });
    }

    private function backupTable(ConnectionDTO $connection, string $table, TemporaryDirectory $temporaryDirectory): string
    {
        try {
            $dsn = "mysql:host={$connection->host};port={$connection->port};dbname={$connection->database}";

            // Check if this table should be structure-only
            $isStructureOnly = in_array($table, $connection->structureOnly);

            $dumpSettings = [
                'compress' => CompressManagerFactory::NONE,
                'no-data' => $isStructureOnly, // Skip data if table is in structureOnly array
                'add-drop-table' => true,
                'single-transaction' => true,
                'lock-tables' => false,
                'add-locks' => true,
                'extended-insert' => true,
                'disable-foreign-keys-check' => true,

                // Only dump this specific table
                'include-tables' => [$table],
            ];

            // Add additional options from connection
            if (! empty($connection->additionalOptions)) {
                $dumpSettings = array_merge($dumpSettings, $connection->additionalOptions);
            }

            $filename = "{$table}.sql";
            $filePath = $temporaryDirectory->path($filename);

            $dump = new IMysqldump\Mysqldump($dsn, $connection->username, $connection->password, $dumpSettings);

            // Dump only this specific table
            $dump->start($filePath);

            if (! file_exists($filePath) || filesize($filePath) === 0) {
                throw BackupException::backupFailed($connection->database, null, [
                    'reason' => "Table '{$table}' dump file was not created or is empty",
                    'table' => $table,
                    'structure_only' => $isStructureOnly,
                ]);
            }

            return $filePath;

        } catch (Exception $e) {
            throw BackupException::backupFailed($connection->database, $e, [
                'reason' => "Failed to backup table '{$table}': ".$e->getMessage(),
                'table' => $table,
                'operation' => 'backup_table',
                'structure_only' => $isStructureOnly ?? false,
            ]);
        }
    }

    private function backupDatabaseStructure(ConnectionDTO $connection, TemporaryDirectory $temporaryDirectory): ?string
    {
        try {
            $dsn = "mysql:host={$connection->host};port={$connection->port};dbname={$connection->database}";

            // --skip-triggers  --no-data  --no-create-db --no-create-info --routines --events
            $dumpSettings = [
                'compress' => CompressManagerFactory::NONE,
                'no-data' => true,
                'no-create-info' => true,
                'routines' => true,
                'events' => true,
                // 'no-create-db' => true,
                // 'triggers' => true,
                // 'add-drop-triggers' => true,
            ];

            $filename = '_database_structure.sql';
            $filePath = $temporaryDirectory->path($filename);

            $dump = new IMysqldump\Mysqldump($dsn, $connection->username, $connection->password, $dumpSettings);
            $dump->start($filePath);

            if (! file_exists($filePath) || filesize($filePath) === 0) {
                // Structure backup is optional, return null if it fails
                return null;
            }

            return $filePath;

        } catch (Exception $e) {
            // Structure backup failure is not critical, log but continue
            logger()->warning('Failed to backup database structure', [
                'database' => $connection->database,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function createZipArchive(array $files, TemporaryDirectory $temporaryDirectory, string $databaseName): string
    {
        $zipPath = $temporaryDirectory->path($databaseName.'_backup.zip');
        $zip = new ZipArchive;

        $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw BackupException::backupFailed($databaseName, null, [
                'reason' => "Failed to create ZIP archive: Error code {$result}",
                'operation' => 'create_zip',
            ]);
        }

        foreach ($files as $file) {
            $filename = basename($file);
            if (! $zip->addFile($file, $filename)) {
                $zip->close();
                throw BackupException::backupFailed($databaseName, null, [
                    'reason' => "Failed to add file '{$filename}' to ZIP archive",
                    'operation' => 'add_file_to_zip',
                    'file' => $filename,
                ]);
            }
        }

        // Add metadata file
        $metadata = [
            'database' => $databaseName,
            'backup_date' => now()->toISOString(),
            'table_count' => count($files) - 1, // Subtract 1 for structure file
            'files' => array_map('basename', $files),
            'version' => '1.0',
        ];

        $zip->addFromString('backup_metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));

        $zip->close();

        if (! file_exists($zipPath) || filesize($zipPath) === 0) {
            throw BackupException::backupFailed($databaseName, null, [
                'reason' => 'ZIP archive was not created or is empty',
                'operation' => 'finalize_zip',
            ]);
        }

        return $zipPath;
    }

    private function validateConnection(ConnectionDTO $connection): void
    {
        if (empty($connection->host)) {
            throw BackupException::invalidConfiguration('host', 'Host cannot be empty');
        }

        if ($connection->port < 1 || $connection->port > 65535) {
            throw BackupException::invalidConfiguration('port', 'Port must be between 1 and 65535');
        }

        if (empty($connection->database)) {
            throw BackupException::invalidConfiguration('database', 'Database name cannot be empty');
        }

        if (empty($connection->username)) {
            throw BackupException::invalidConfiguration('username', 'Username cannot be empty');
        }

        if (empty($connection->password)) {
            throw BackupException::invalidConfiguration('password', 'Password cannot be empty');
        }
    }
}
