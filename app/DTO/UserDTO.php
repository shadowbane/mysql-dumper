<?php

namespace App\DTO;

use Spatie\LaravelData\Data;

class UserDTO extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public bool $is_administrator = false,
    ) {}
}
