<?php

namespace App\DTO;

use Illuminate\Validation\Rules\Password;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class ProfileUpdateDTO extends Data
{
    public function __construct(
        public string $name,
        public ?string $password = null,
        public ?string $password_confirmation = null,
    ) {}

    public static function rules(ValidationContext $context): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ];
    }
}
