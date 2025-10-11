<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'is_administrator',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the roles that belong to the user.
     *
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withTimestamps();
    }

    /**
     * Get the data sources that the user has access to.
     *
     * @return BelongsToMany
     */
    public function dataSources(): BelongsToMany
    {
        return $this->belongsToMany(DataSource::class, 'data_source_user')
            ->withTimestamps();
    }

    /**
     * Check if the user has a specific role.
     *
     * @param  string  $roleSlug
     * @return bool
     */
    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()->where('slug', $roleSlug)->exists();
    }

    /**
     * Check if the user is an administrator.
     *
     * @return bool
     */
    public function isAdministrator(): bool
    {
        return $this->hasRole('administrator');
    }

    public function getIsAdministratorAttribute(): bool
    {
        return $this->isAdministrator();
    }

    /**
     * Check if the user has access to a specific data source.
     *
     * @param  string  $dataSourceId
     * @return bool
     */
    public function hasAccessToDataSource(string $dataSourceId): bool
    {
        // Administrators have access to all data sources
        if ($this->isAdministrator()) {
            return true;
        }

        // Members only have access to assigned data sources
        return $this->dataSources()->where('data_sources.id', $dataSourceId)->exists();
    }
}
