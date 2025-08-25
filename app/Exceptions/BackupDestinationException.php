<?php

namespace App\Exceptions;

class BackupDestinationException extends BackupException
{
    public static function configIsMissingClass(string $disk): self
    {
        return new self("Backup configuration for {$disk} is missing.");
    }

    public static function serviceDoesNotImplementInterface(string $disk): self
    {
        return new self("Backup service for {$disk} does not implement interface.");
    }

    public static function downloadDisabled(): self
    {
        return new self('Backup download disabled for this disk.');
    }
}
