<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Cache\LockTimeoutException;

abstract class Controller
{
    protected const int LOCK_TIMEOUT = 30; // Lock timeout in seconds

    protected const int LOCK_WAIT = 5; // Maximum time to wait for lock in seconds

    /**
     * Execute a store operation with atomic locking.
     *
     * @param  string  $lockName
     * @param  \Closure  $callback
     *
     * @throws LockTimeoutException
     *
     * @return mixed
     */
    protected function executeStoreWithLock(string $lockName, \Closure $callback): mixed
    {
        // Create lock based on company_id
        $lock = cache()->lock($lockName, self::LOCK_TIMEOUT);

        try {
            // Try to get the lock with a maximum wait time
            $lockAcquired = $lock->block(self::LOCK_WAIT);

            if (! $lockAcquired) {
                throw new \RuntimeException(
                    'Could not acquire lock. Another entry is currently being created.'
                );
            }

            // Execute the store operation
            return $callback();
        } catch (\Exception $e) {
            throw $e;
        } finally {
            // Always release the lock, even if an exception occurred
            optional($lock)->release();
        }
    }
}
