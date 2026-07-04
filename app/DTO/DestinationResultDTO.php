<?php

namespace App\DTO;

use Spatie\LaravelData\Data;

class DestinationResultDTO extends Data
{
    public function __construct(
        public string $destinationId,
        public bool $success,
        public ?string $filePath = null,
        public ?string $error = null,
    ) {}
}
