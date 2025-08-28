<?php

return [
    /*
     * Database Backup Destinations
     *
     * Configure which destination classes should be enabled for database backups.
     * Each destination class must implement App\Contracts\BackupDestinationInterface.
     */

    'destinations' => json_validate(env('BACKUP_DESTINATIONS', '[]'))
        ? json_decode(env('BACKUP_DESTINATIONS', '[]'), true)
        : [],

    /*
     * Database Dump Compression
     *
     * Configure compression settings for mysqldump-php.
     * Available options: 'gzip', 'bzip2', 'gzipstream', 'zstd', 'lz4', 'none'
     *
     * Compression levels:
     * - Gzip: 0-9 (default: 0)
     * - Bzip2: 1-9 (default: 4)
     * - Zstd: 1-22 (default: 3)
     * - Lz4: 1-12 (default: 1)
     */
    'compression' => [
        'method' => env('DB_DUMP_COMPRESSION_METHOD', 'gzip'),
        'level' => env('DB_DUMP_COMPRESSION_LEVEL', 6),
    ],
];
