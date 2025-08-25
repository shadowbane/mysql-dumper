<?php

namespace App\Services;

use App\Contracts\BackupDestinationInterface;
use App\Exceptions\BackupDestinationException;
use App\Models\BackupLog;
use App\Models\File;
use Exception;
use Illuminate\Support\Collection;

class BackupDestinationService
{
    private array $destinations = [];

    public function __construct()
    {
        // Register default destinations
        $this->registerDestinations();
    }

    /**
     * Register a backup destination.
     */
    public function register(BackupDestinationInterface $destination): void
    {
        $this->destinations[$destination->getDestinationId()] = $destination;
    }

    /**
     * Get all registered destinations.
     *
     * @return BackupDestinationInterface[]
     */
    public function getDestinations(): array
    {
        return $this->destinations;
    }

    /**
     * Get destinations that are enabled for a specific backup log.
     *
     * @param  BackupLog  $backupLog
     * @return BackupDestinationInterface[]
     */
    public function getEnabledDestinations(BackupLog $backupLog): array
    {
        return array_filter($this->destinations, function (BackupDestinationInterface $destination) use ($backupLog) {
            return $destination->isEnabled($backupLog);
        });
    }

    /**
     * Get a specific destination by ID.
     */
    public function getDestination(string $destinationId): ?BackupDestinationInterface
    {
        return $this->destinations[$destinationId] ?? null;
    }

    /**
     * Register backup destinations based on configuration.
     */
    private function registerDestinations(): void
    {
        $enabledDestinations = $this->getConfig();

        foreach ($enabledDestinations as $destinationConfig) {
            try {
                $destination = $this->initializeDestinationService($destinationConfig);
                $this->register($destination);
            } catch (BackupDestinationException $e) {
                logger()->error($e->getMessage());

                continue;
            } catch (Exception $e) {
                // Log error and continue with other destinations
                logger()->warning("Failed to register backup destination: {$destinationConfig['disk']}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  File  $backupFile
     *
     * @throws BackupDestinationException
     *
     * @return BackupDestinationInterface|null
     */
    public function getDestinationFromFile(File $backupFile): ?BackupDestinationInterface
    {
        $disk = $backupFile->disk;
        $destinationConfig = $this->getConfig()
            ->where('disk', $disk)
            ->first();

        if (! $destinationConfig) {
            throw new \Exception("Backup disk {$disk} not found. Is it enabled?");
        }

        return $this->initializeDestinationService($destinationConfig);
    }

    /**
     * @return Collection
     */
    private function getConfig(): Collection
    {
        return collect(config('database-backup.destinations', []));
    }

    /**
     * @param  $destinationConfig
     *
     * @throws BackupDestinationException
     *
     * @return BackupDestinationInterface
     */
    private function initializeDestinationService($destinationConfig): BackupDestinationInterface
    {
        $destinationClass = $destinationConfig['class'];
        if (! class_exists($destinationClass)) {
            throw BackupDestinationException::configIsMissingClass($destinationConfig['disk']);
        }

        // Check if the class implements the interface
        if (! in_array(BackupDestinationInterface::class, class_implements($destinationClass) ?: [])) {
            throw BackupDestinationException::serviceDoesNotImplementInterface($destinationConfig['disk']);
        }

        // Instantiate the destination class
        return new $destinationClass($destinationConfig['disk'], $destinationConfig['path'] ?? '/');
    }
}
