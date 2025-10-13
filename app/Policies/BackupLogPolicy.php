<?php

namespace App\Policies;

use App\Models\BackupLog;
use App\Models\User;

class BackupLogPolicy
{
    /**
     * Determine if user can see the backup log.
     */
    public function viewAny(User $user, BackupLog $backupLog): bool
    {
        return $user->hasAccessToDataSource($backupLog->data_source_id);
    }

    /**
     * Determine if user can manage lock of backup log.
     */
    public function manageLock(User $user, BackupLog $backupLog): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine if user can delete backup log.
     */
    public function delete(User $user, BackupLog $backupLog): bool
    {
        return $user->isAdministrator();
    }
}
