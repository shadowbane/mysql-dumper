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

        #[Computed]
        public ?string $directoryName = null,
    ) {
        // The file may already be gone when this DTO is reconstructed from
        // persisted state (backups:reconcile after a lost job).
        $this->filename = basename($this->fullPath);
        $this->fileSize = file_exists($this->fullPath) ? filesize($this->fullPath) : null;
        $this->directoryName = dirname($this->fullPath);
    }
}
