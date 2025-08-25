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
            [
                'class' => \App\Services\Destinations\LocalBackupDestination::class,
                'disk' => 'local',
                'path' => 'database-backups',
            ],

            // Add your custom destinations here
            [
                'class' => \App\Services\Destinations\S3BackupDestination::class,
                'disk' => 's3',
                'path' => 'database-backups',
            ],
            // [
            //     'class' => \App\Services\Destinations\SftpBackupDestination::class,
            //     'disk' => 'sftp',
            //     'path' => 'database-backups',
            // ],
        ],
    ],
];
