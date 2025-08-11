<?php

namespace App\Exceptions;

use Exception;

class BackupException extends Exception
{
    public const CONNECTION_FAILED = 'CONNECTION_FAILED';

    public const BACKUP_FAILED = 'BACKUP_FAILED';

    public const FILE_WRITE_FAILED = 'FILE_WRITE_FAILED';

    public const INVALID_CONFIGURATION = 'INVALID_CONFIGURATION';

    public const DATABASE_NOT_FOUND = 'DATABASE_NOT_FOUND';

    public const INSUFFICIENT_PERMISSIONS = 'INSUFFICIENT_PERMISSIONS';

    public const DISK_SPACE_FULL = 'DISK_SPACE_FULL';

    public const COMMAND_EXECUTION_FAILED = 'COMMAND_EXECUTION_FAILED';

    private ?string $errorCode;
    private ?array $context;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?Exception $previous = null,
        ?string $errorCode = null,
        ?array $context = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
        $this->context = $context ?? [];
    }

    public static function connectionFailed(string $host, int $port, ?Exception $previous = null, ?string $reason = null): self
    {
        $message = "Failed to connect to database at {$host}:{$port}";
        if ($reason) {
            $message .= ". Reason: {$reason}";
        } elseif ($previous) {
            $message .= '. Reason: '.$previous->getMessage();
        }

        return new self(
            message: $message,
            previous: $previous,
            errorCode: self::CONNECTION_FAILED,
            context: [
                'host' => $host,
                'port' => $port,
                'reason' => $reason ?? ($previous ? $previous->getMessage() : 'Unknown'),
                'error_type' => $previous ? get_class($previous) : null,
            ]
        );
    }

    public static function backupFailed(string $database, ?Exception $previous = null, ?array $context = null): self
    {
        $reason = $context['reason'] ?? ($previous ? $previous->getMessage() : 'Unknown error');
        $message = "Failed to backup database '{$database}'. Reason: {$reason}";

        return new self(
            message: $message,
            previous: $previous,
            errorCode: self::BACKUP_FAILED,
            context: array_merge([
                'database' => $database,
                'reason' => $reason,
                'error_type' => $previous ? get_class($previous) : null,
                'timestamp' => now()->toISOString(),
            ], $context ?? [])
        );
    }

    public static function fileWriteFailed(string $path, ?Exception $previous = null): self
    {
        return new self(
            message: "Failed to write backup file to '{$path}'",
            previous: $previous,
            errorCode: self::FILE_WRITE_FAILED,
            context: ['path' => $path]
        );
    }

    public static function invalidConfiguration(string $field, ?string $reason = null): self
    {
        $message = "Invalid configuration for field '{$field}'";
        if ($reason) {
            $message .= ": {$reason}";
        }

        return new self(
            message: $message,
            errorCode: self::INVALID_CONFIGURATION,
            context: ['field' => $field, 'reason' => $reason]
        );
    }

    public static function databaseNotFound(string $database): self
    {
        return new self(
            message: "Database '{$database}' not found",
            errorCode: self::DATABASE_NOT_FOUND,
            context: ['database' => $database]
        );
    }

    public static function insufficientPermissions(string $operation, ?array $context = null): self
    {
        return new self(
            message: "Insufficient permissions for operation: {$operation}",
            errorCode: self::INSUFFICIENT_PERMISSIONS,
            context: array_merge(['operation' => $operation], $context ?? [])
        );
    }

    public static function diskSpaceFull(string $disk): self
    {
        return new self(
            message: "Insufficient disk space on '{$disk}'",
            errorCode: self::DISK_SPACE_FULL,
            context: ['disk' => $disk]
        );
    }

    public static function commandExecutionFailed(string $command, int $exitCode, string $output = ''): self
    {
        return new self(
            message: "Command execution failed with exit code {$exitCode}: {$command}",
            errorCode: self::COMMAND_EXECUTION_FAILED,
            context: [
                'command' => $command,
                'exit_code' => $exitCode,
                'output' => $output,
            ]
        );
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context ?? [];
    }

    public function getReason(): string
    {
        $context = $this->getContext();

        // Try to get reason from context first
        if (isset($context['reason']) && ! empty($context['reason'])) {
            return $context['reason'];
        }

        // Fall back to previous exception message
        if ($this->getPrevious()) {
            return $this->getPrevious()->getMessage();
        }

        // Fall back to the main message
        return $this->getMessage();
    }

    public function getDetailedReason(): array
    {
        $reason = [
            'primary_reason' => $this->getReason(),
            'error_code' => $this->getErrorCode(),
            'context' => $this->getContext(),
        ];

        if ($this->getPrevious()) {
            $reason['underlying_error'] = [
                'type' => get_class($this->getPrevious()),
                'message' => $this->getPrevious()->getMessage(),
                'code' => $this->getPrevious()->getCode(),
            ];
        }

        return $reason;
    }

    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'error_code' => $this->getErrorCode(),
            'reason' => $this->getReason(),
            'detailed_reason' => $this->getDetailedReason(),
            'context' => $this->getContext(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString(),
        ];
    }
}
