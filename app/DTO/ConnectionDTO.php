<?php

namespace App\DTO;

use Spatie\LaravelData\Data;

class ConnectionDTO extends Data
{
    public function __construct(
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        public string $password,
        public bool $compression = true,
        public array $skippedTables = [],
        public array $structureOnly = [],
        public ?string $connectionName = null,
        public array $additionalOptions = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            host: $data['host'],
            port: $data['port'] ?? 3306,
            database: $data['database'],
            username: $data['username'],
            password: $data['password'],
            compression: $data['compression'] ?? true,
            skippedTables: $data['skipped_tables'] ?? [],
            structureOnly: $data['structure_only'] ?? [],
            connectionName: $data['connection_name'] ?? null,
            additionalOptions: $data['additional_options'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password,
            'compression' => $this->compression,
            'skipped_tables' => $this->skippedTables,
            'structure_only' => $this->structureOnly,
            'connection_name' => $this->connectionName,
            'additional_options' => $this->additionalOptions,
        ];
    }

    public function getDatabaseConfig(): array
    {
        return [
            'driver' => 'mysql',
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                \PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ];
    }
}
