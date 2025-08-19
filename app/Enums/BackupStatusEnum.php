<?php

namespace App\Enums;

enum BackupStatusEnum: string
{
    case pending = 'Pending';
    case running = 'Running';
    case backup_ready = 'Backup Ready';
    case storing_to_destinations = 'Storing to Destinations';
    case completed = 'Completed';
    case partially_failed = 'Partially Failed';
    case failed = 'Failed';
}
