<?php

return [
    /*
     * Database Backup Destinations
     *
     * Configure which destination classes should be enabled for database backups.
     * Each destination class must implement App\Contracts\BackupDestinationInterface.
     */
    'destinations' => [
        /*
         * List of destination classes to enable for database backups.
         * Add or remove destinations as needed.
         */
        'enabled' => [
            \App\Services\Destinations\LocalBackupDestination::class,

            // Add your custom destinations here
            // \App\Services\Destinations\S3BackupDestination::class,
            // \App\Services\Destinations\R2BackupDestination::class,
        ],
    ],
];
