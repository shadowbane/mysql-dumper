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
];
