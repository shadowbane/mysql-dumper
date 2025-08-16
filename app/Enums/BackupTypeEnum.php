<?php

namespace App\Enums;

enum BackupTypeEnum: string
{
    case automated = 'Automated';
    case manual = 'Manual';
}
