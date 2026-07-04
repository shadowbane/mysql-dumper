<?php

namespace App\Console\Commands;

use App\DTO\BackupFileDTO;
use App\DTO\DestinationResultDTO;
use App\Enums\BackupStatusEnum;
use App\Events\AllDestinationsProcessedEvent;
use App\Jobs\StoreBackupToDestinationJob;
use App\Models\BackupLog;
use Carbon\CarbonInterface;
use Exception;
use Illuminate\Console\Command;
use Spatie\LaravelData\DataCollection;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class ReconcileStuckBackupsCommand extends Command
{
    protected $signature = 'backups:reconcile {--dry-run : Report what would be done without changing anything}';
    protected $description = 'Recover backups stuck by lost jobs, dead workers, or destination outages: resume pending deliveries or finalize their status';

    public function handle(): int
    {
        $threshold = now()->subMinutes(
            max(1, (int) config('database-backup.delivery.stuck_after_minutes', 120))
        );
        $isDryRun = (bool) $this->option('dry-run');

        $this->reconcileStuckRunning($threshold, $isDryRun);
        $this->reconcileStuckStoring($threshold, $isDryRun);

        return self::SUCCESS;
    }

    /**
     * A backup stuck in "running" past the threshold means the dump worker
     * died mid-dump (CreateBackupJob has tries=1 and a 1h timeout, so its
     * failed() hook never ran). Nothing can resume a dump — mark it failed.
     */
    private function reconcileStuckRunning(CarbonInterface $threshold, bool $isDryRun): void
    {
        $stuck = BackupLog::where('status', BackupStatusEnum::running)
            ->where('updated_at', '<', $threshold)
            ->get();

        foreach ($stuck as $backupLog) {
            if ($isDryRun) {
                $this->line("[dry-run] Would mark stuck running backup {$backupLog->id} as failed");

                continue;
            }

            $backupLog->markAsFailed(new Exception(
                'Backup stuck in running state beyond the stuck_after_minutes threshold — the dump worker likely died'
            ));

            $this->info("Marked stuck running backup {$backupLog->id} as failed");
        }
    }

    /**
     * A backup stuck in "backup_ready" means one or more destination jobs were
     * lost (worker crash, dropped connection, lost delayed retry). Per stale
     * destination: resume delivery if the temp archive still exists, otherwise
     * record a final failure; once every destination is terminal, fire
     * AllDestinationsProcessedEvent so the normal listener finalizes status.
     */
    private function reconcileStuckStoring(CarbonInterface $threshold, bool $isDryRun): void
    {
        $stuck = BackupLog::where('status', BackupStatusEnum::backup_ready)
            ->where('updated_at', '<', $threshold)
            ->get();

        foreach ($stuck as $backupLog) {
            $summary = $backupLog->getDestinationStatusSummary();

            if (empty($summary)) {
                if ($isDryRun) {
                    $this->line("[dry-run] Would mark backup {$backupLog->id} as failed (storing phase never started)");

                    continue;
                }

                $backupLog->markAsFailed(new Exception(
                    'Storing phase never started — destination jobs were lost before any delivery attempt'
                ));
                $this->info("Marked backup {$backupLog->id} as failed (storing phase never started)");

                continue;
            }

            $tempFilePath = $this->getTempFilePath($backupLog);
            $tempFileExists = $tempFilePath !== null && file_exists($tempFilePath);

            $redispatched = 0;
            $declaredDead = 0;

            foreach ($summary as $destinationId => $data) {
                if (! in_array($data['status'], ['queued', 'processing', 'retrying'], true)) {
                    continue; // already terminal
                }

                // Only touch destinations with no timeline activity within the
                // threshold — a recent attempt or a still-pending delayed retry
                // must be left alone to avoid duplicate deliveries.
                if ($data['latest_timeline']->updated_at->gte($threshold)) {
                    continue;
                }

                if ($tempFileExists) {
                    if ($isDryRun) {
                        $this->line("[dry-run] Would re-dispatch delivery of backup {$backupLog->id} to {$destinationId}");

                        continue;
                    }

                    StoreBackupToDestinationJob::dispatch(
                        $backupLog->id,
                        $destinationId,
                        $this->makeFileData($tempFilePath),
                        [],
                        $data['retries']
                    );
                    $redispatched++;
                } else {
                    if ($isDryRun) {
                        $this->line("[dry-run] Would mark delivery of backup {$backupLog->id} to {$destinationId} as failed (temp file gone)");

                        continue;
                    }

                    $data['latest_timeline']->update([
                        'metadata' => array_merge($data['latest_timeline']->metadata ?? [], [
                            'success' => false,
                            'error_message' => 'Delivery interrupted and the temporary backup file is no longer available (reconciled)',
                            'failed_at' => now()->toISOString(),
                            'will_retry' => false,
                            'retry_scheduled' => false,
                        ]),
                    ]);
                    $declaredDead++;
                }
            }

            if ($isDryRun) {
                continue;
            }

            if ($redispatched > 0) {
                $this->info("Backup {$backupLog->id}: re-dispatched {$redispatched} stale destination deliveries");

                continue; // the resumed jobs will finalize via their own aggregation
            }

            // No deliveries left in flight — finalize through the normal
            // aggregation listener if every destination is now terminal.
            if ($backupLog->areAllDestinationsCompleted()) {
                AllDestinationsProcessedEvent::dispatch(
                    $backupLog,
                    $this->makeFileData($tempFilePath ?? ''),
                    $this->buildResults($backupLog)
                );
                $this->info("Backup {$backupLog->id}: finalized ({$declaredDead} destinations declared dead)");
            }
        }
    }

    private function getTempFilePath(BackupLog $backupLog): ?string
    {
        $timeline = $backupLog->timelines()
            ->where('status', BackupStatusEnum::backup_ready)
            ->whereNotNull('metadata->temp_file_path')
            ->orderBy('created_at', 'desc')
            ->first();

        return $timeline?->metadata['temp_file_path'] ?? null;
    }

    private function makeFileData(string $fullPath): BackupFileDTO
    {
        return new BackupFileDTO(
            temporaryDirectory: new TemporaryDirectory($fullPath !== '' ? dirname($fullPath) : ''),
            fullPath: $fullPath,
        );
    }

    /**
     * @return DataCollection<int, DestinationResultDTO>
     */
    private function buildResults(BackupLog $backupLog): DataCollection
    {
        $results = [];

        foreach ($backupLog->getDestinationStatusSummary() as $destinationId => $data) {
            $results[] = new DestinationResultDTO(
                destinationId: $destinationId,
                success: $data['status'] === 'completed',
                filePath: $data['file_path'],
                error: $data['last_error'],
            );
        }

        return new DataCollection(DestinationResultDTO::class, $results);
    }
}
