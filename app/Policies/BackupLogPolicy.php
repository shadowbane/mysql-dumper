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
     * Determine if user can lock the backup log.
     */
    public function lock(User $user, BackupLog $backupLog): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        return (bool) config('database-backup.members.can_lock', false)
            && $user->hasAccessToDataSource($backupLog->data_source_id);
    }

    /**
     * Determine if user can unlock the backup log.
     */
    public function unlock(User $user, BackupLog $backupLog): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        return (bool) config('database-backup.members.can_unlock', false)
            && $user->hasAccessToDataSource($backupLog->data_source_id);
    }

    /**
     * Determine if user can delete backup log.
     */
    public function delete(User $user, BackupLog $backupLog): bool
    {
        return $user->isAdministrator();
    }
}
