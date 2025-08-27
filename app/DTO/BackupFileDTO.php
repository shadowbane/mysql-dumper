<?php

namespace App\DTO;

use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class BackupFileDTO extends Data
{
    public function __construct(
        public TemporaryDirectory $temporaryDirectory,
        public string $fullPath,

        #[Computed]
        public ?int $fileSize = null,

        #[Computed]
        public ?string $filename = null,
    ) {
        $this->filename = basename($this->fullPath);
        $this->fileSize = filesize($this->fullPath);
    }
}
