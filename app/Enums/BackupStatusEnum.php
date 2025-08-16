<?php

namespace App\Enums;

enum BackupStatusEnum: string
{
    case pending = 'Pending';
    case running = 'Running';
    case completed = 'Completed';
    case failed = 'Failed';
}
