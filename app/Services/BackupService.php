<?php

namespace App\Services;

use App\Contracts\BackupServiceInterface;
use App\DTO\BackupFileDTO;
use App\DTO\ConnectionDTO;
use App\Events\BackupFailedEvent;
use App\Events\BackupReadyEvent;
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

            // Perform backup
            $fileData = $this->backup($connectionDTO, $backupLog);

            $metadata = [
                'database' => $dataSource->database,
                'tables_backed_up' => count($connectionDTO->skippedTables ? array_diff($this->getTables($connectionDTO), $connectionDTO->skippedTables) : $this->getTables($connectionDTO)),
                'structure_only_tables' => $connectionDTO->structureOnly,
                'skipped_tables' => $connectionDTO->skippedTables,
            ];

            // Mark backup as ready and emit event for destination storage
            $backupLog->markAsBackupReady(
                metadata: $metadata
            );

            // Emit event for destination handlers
            BackupReadyEvent::dispatch($backupLog, $fileData, $metadata);

        } catch (Exception $e) {
            // Mark backup as failed
            $backupLog->markAsFailed($e);

            // Emit backup failed event
            BackupFailedEvent::dispatch($backupLog, $e);

            // Re-throw to trigger job failure handling
            throw $e;
        }
    }

    public function backup(ConnectionDTO $connection, BackupLog $backupLog): \App\DTO\BackupFileDTO
    {
        $this->validateConnection($connection);

        $temporaryDirectoryPath = storage_path("app/private/temp/{$backupLog->data_source_id}");
        $temporaryDirectory = TemporaryDirectory::make($temporaryDirectoryPath);
        try {
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

            // Get and backup views
            $views = $this->getViews($connection);
            foreach ($views as $view) {
                if (! in_array($view, $connection->skippedTables)) {
                    try {
                        $viewFile = $this->backupView($connection, $view, $temporaryDirectory);
                        $tableFiles[] = $viewFile;
                    } catch (Exception $e) {
                        // Log view backup failure but continue
                        $backupLog->addWarning([
                            'message' => "Failed to backup view '{$view}'",
                            'view' => $view,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Create database structure dump (no data)
            $structureFile = $this->backupDatabaseStructure($connection, $temporaryDirectory, $backupLog);
            if ($structureFile) {
                $tableFiles[] = $structureFile;
            }

            // Create ZIP archive and return the temporary file path
            $zipPath = $this->createZipArchive($tableFiles, $temporaryDirectory, $connection->database);

            return new BackupFileDTO(
                temporaryDirectory: $temporaryDirectory,
                fullPath: $zipPath,
            );
        } catch (Exception $e) {
            $temporaryDirectory->delete();

            if ($e instanceof BackupException) {
                throw $e;
            }

            throw BackupException::backupFailed($connection->database, $e, [
                'reason' => $e->getMessage(),
            ]);
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
                WHERE table_schema = ? AND table_type = \'BASE TABLE\'
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

    public function getViews(ConnectionDTO $connection): array
    {
        try {
            $this->validateConnection($connection);

            $configName = 'temp_connection_'.uniqid();
            Config::set("database.connections.{$configName}", $connection->getDatabaseConfig());

            $result = DB::connection($configName)->select('
                SELECT table_name
                FROM information_schema.tables
                WHERE table_schema = ? AND table_type = \'VIEW\'
                ORDER BY table_name
            ', [$connection->database]);

            Config::set("database.connections.{$configName}", null);

            return array_map(fn($table) => $table->TABLE_NAME, $result);

        } catch (Exception $e) {
            throw BackupException::backupFailed($connection->database, $e, [
                'operation' => 'get_views',
                'reason' => $e->getMessage(),
            ]);
        }
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
                'compress' => $this->getCompressionMethod(),
                'compress-level' => $this->getCompressionLevel(),
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

            $fileExtension = $this->getFileExtension($dumpSettings['compress']);

            $filename = "{$table}.$fileExtension";
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

    private function backupView(ConnectionDTO $connection, string $view, TemporaryDirectory $temporaryDirectory): string
    {
        try {
            $configName = 'temp_connection_'.uniqid();
            Config::set("database.connections.{$configName}", $connection->getDatabaseConfig());

            // Get the view definition
            $result = DB::connection($configName)->select('SHOW CREATE VIEW `'.$view.'`');

            Config::set("database.connections.{$configName}", null);

            if (empty($result)) {
                throw new Exception("Could not retrieve definition for view '{$view}'");
            }

            $viewDefinition = $result[0]->{'Create View'};

            // Create the SQL content for the view
            $sqlContent = "-- View: {$view}\n";
            $sqlContent .= "DROP VIEW IF EXISTS `{$view}`;\n";
            $sqlContent .= $viewDefinition.";\n";

            $filename = "view_{$view}.sql";
            $filePath = $temporaryDirectory->path($filename);

            if (file_put_contents($filePath, $sqlContent) === false) {
                throw new Exception('Failed to write view definition to file');
            }

            return $filePath;

        } catch (Exception $e) {
            throw BackupException::backupFailed($connection->database, $e, [
                'reason' => "Failed to backup view '{$view}': ".$e->getMessage(),
                'view' => $view,
                'operation' => 'backup_view',
            ]);
        }
    }

    private function backupDatabaseStructure(ConnectionDTO $connection, TemporaryDirectory $temporaryDirectory, BackupLog $backupLog): ?string
    {
        try {
            $dsn = "mysql:host={$connection->host};port={$connection->port};dbname={$connection->database}";

            // --skip-triggers  --no-data  --no-create-db --no-create-info --routines --events
            $dumpSettings = [
                'compress' => $this->getCompressionMethod(),
                'compress-level' => $this->getCompressionLevel(),
                'no-data' => true,
                'no-create-info' => true,
                'routines' => true,
                'events' => true,
                // 'no-create-db' => true,
                // 'triggers' => true,
                // 'add-drop-triggers' => true,
            ];

            $fileExtension = $this->getFileExtension($dumpSettings['compress']);
            $filename = "_database_structure.{$fileExtension}";
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

            $backupLog->addWarning([
                'message' => 'Failed to backup database structure',
                'database' => $connection->database,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function createZipArchive(array $files, TemporaryDirectory $temporaryDirectory, string $databaseName): string
    {
        $filename = $databaseName.'_'.now()->format('YmdHis').'.zip';
        $zipPath = $temporaryDirectory->path($filename);
        $zip = new ZipArchive;

        $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw BackupException::backupFailed($databaseName, null, [
                'reason' => "Failed to create ZIP archive: Error code {$result}",
                'operation' => 'create_zip',
            ]);
        }

        // Set maximum compression level (9)
        $zip->setCompressionName('*', ZipArchive::CM_DEFLATE, 9);

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

        // Add extractor script if we have compressed files
        $hasCompressedFiles = collect($files)->some(fn($file) => str_ends_with($file, '.gz') ||
            str_ends_with($file, '.bz2') ||
            str_ends_with($file, '.zst') ||
            str_ends_with($file, '.lz4')
        );
        if ($hasCompressedFiles) {
            $extractorScript = $this->generateExtractorScript();
            $zip->addFromString('extractor.sh', $extractorScript);
        }

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

    private function getCompressionMethod(): string
    {
        $method = config('database-backup.compression.method', 'gzip');

        return match ($method) {
            'gzip' => CompressManagerFactory::GZIP,
            'bzip2' => CompressManagerFactory::BZIP2,
            'gzipstream' => CompressManagerFactory::GZIPSTREAM,
            'zstd' => CompressManagerFactory::ZSTD,
            'lz4' => CompressManagerFactory::LZ4,
            'none' => CompressManagerFactory::NONE,
            default => CompressManagerFactory::GZIP,
        };
    }

    private function getFileExtension(string $compressionMethod): string
    {
        return match ($compressionMethod) {
            CompressManagerFactory::NONE => 'sql',
            CompressManagerFactory::GZIP => 'sql.gz',
            CompressManagerFactory::GZIPSTREAM => 'sql.gz',
            CompressManagerFactory::BZIP2 => 'sql.bz2',
            CompressManagerFactory::ZSTD => 'sql.zst',
            CompressManagerFactory::LZ4 => 'sql.lz4',
            default => 'sql.gz',
        };
    }

    private function getCompressionLevel(): int
    {
        return (int) config('database-backup.compression.level', 6);
    }

    private function generateExtractorScript(): string
    {
        return <<<'BASH'
#!/bin/bash

# MySQL Backup Extractor Script
# This script extracts compressed .gz files to readable .sql files

echo "MySQL Backup Extractor"
echo "======================="
echo

# Check if gzip is available
if ! command -v gzip &> /dev/null; then
    echo "Error: gzip command not found. Please install gzip to extract compressed files."
    exit 1
fi

# Function to extract a single .gz file
extract_file() {
    local gz_file="$1"
    local sql_file="${gz_file%.gz}"

    if [[ -f "$gz_file" ]]; then
        echo "Extracting: $gz_file -> $sql_file"
        if gunzip -c "$gz_file" > "$sql_file" 2>/dev/null; then
            echo "✓ Successfully extracted: $sql_file"
        else
            echo "✗ Failed to extract: $gz_file"
            return 1
        fi
    else
        echo "✗ File not found: $gz_file"
        return 1
    fi
}

# Main extraction logic
extracted_count=0
failed_count=0

echo "Scanning for .gz files..."
echo

# Find and extract all .gz files
for gz_file in *.gz; do
    if [[ -f "$gz_file" ]]; then
        if extract_file "$gz_file"; then
            ((extracted_count++))
        else
            ((failed_count++))
        fi
        echo
    fi
done

# Summary
echo "Extraction Summary:"
echo "=================="
echo "Successfully extracted: $extracted_count files"
echo "Failed extractions: $failed_count files"
echo

if [[ $extracted_count -gt 0 ]]; then
    echo "✓ Extraction completed!"
    echo
    echo "Available SQL files:"
    ls -la *.sql 2>/dev/null || echo "No SQL files found."
else
    echo "No .gz files were found to extract."
fi

BASH;
    }
}
