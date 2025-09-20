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

    'cleanup' => [
        /*
         * The strategy that will be used to cleanup old backups. The default strategy
         * will keep all backups for a certain amount of days. After that period only
         * a daily backup will be kept. After that period only weekly backups will
         * be kept and so on.
         *
         * No matter how you configure it the default strategy will never
         * delete the newest backup.
         */
        'default_strategy' => [
            /*
             * The number of days for which backups must be kept.
             */
            'keep_all_backups_for_days' => 7,

            /*
             * After the "keep_all_backups_for_days" period is over, the most recent backup
             * of that day will be kept. Older backups within the same day will be removed.
             * If you create backups only once a day, no backups will be removed yet.
             */
            'keep_daily_backups_for_days' => 16,

            /*
             * After the "keep_daily_backups_for_days" period is over, the most recent backup
             * of that week will be kept. Older backups within the same week will be removed.
             * If you create backups only once a week, no backups will be removed yet.
             */
            'keep_weekly_backups_for_weeks' => 8,

            /*
             * After the "keep_weekly_backups_for_weeks" period is over, the most recent backup
             * of that month will be kept. Older backups within the same month will be removed.
             */
            'keep_monthly_backups_for_months' => 4,

            /*
             * After the "keep_monthly_backups_for_months" period is over, the most recent backup
             * of that year will be kept. Older backups within the same year will be removed.
             */
            'keep_yearly_backups_for_years' => 2,
        ],

        /*
         * The number of attempts, in case the cleanup command encounters an exception
         */
        'tries' => 3,
    ],
];
