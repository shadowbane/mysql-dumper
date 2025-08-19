<?php

namespace App\Services;

use App\Contracts\BackupDestinationInterface;
use App\Models\BackupLog;
use Exception;
use Illuminate\Support\Facades\Config;

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
        $enabledDestinations = Config::get('database-backup.destinations.enabled', []);

        foreach ($enabledDestinations as $destinationClass) {
            try {
                if (! class_exists($destinationClass)) {
                    continue;
                }

                // Check if the class implements the interface
                if (! in_array(BackupDestinationInterface::class, class_implements($destinationClass) ?: [])) {
                    continue;
                }

                // Instantiate the destination class
                $destination = new $destinationClass;
                $this->register($destination);

            } catch (Exception $e) {
                // Log error and continue with other destinations
                logger()->warning("Failed to register backup destination: {$destinationClass}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
