<?php

namespace App\DTO;

use App\Models\User;
use Spatie\LaravelData\Data;

class UserDTO extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public bool $is_administrator = false,
        public bool $can_lock_backup = false,
        public bool $can_unlock_backup = false,
    ) {}

    public static function fromUser(User $user): self
    {
        $isAdmin = $user->isAdministrator();

        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            is_administrator: $isAdmin,
            can_lock_backup: $isAdmin || (bool) config('database-backup.members.can_lock', false),
            can_unlock_backup: $isAdmin || (bool) config('database-backup.members.can_unlock', false),
        );
    }
}
