<?php

namespace App\Policies;

use App\Models\DataSource;
use App\Models\User;

class DataSourcePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view the data source list
        // (but the list will be filtered based on their permissions)
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DataSource $dataSource): bool
    {
        return $user->hasAccessToDataSource($dataSource->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only administrators can create new data sources
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DataSource $dataSource): bool
    {
        // Administrators can update any data source
        // Members can update data sources they have access to
        // return $user->hasAccessToDataSource($dataSource->id);
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DataSource $dataSource): bool
    {
        // Only administrators can delete data sources
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DataSource $dataSource): bool
    {
        // Only administrators can restore data sources
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DataSource $dataSource): bool
    {
        // Only administrators can force delete data sources
        return $user->isAdministrator();
    }
}
