<?php

namespace Tests\Feature;

use App\Contracts\BackupDestinationInterface;
use App\DTO\BackupFileDTO;
use App\Enums\BackupStatusEnum;
use App\Enums\BackupTypeEnum;
use App\Events\AllDestinationsProcessedEvent;
use App\Events\BackupReadyEvent;
use App\Jobs\StoreBackupToDestinationJob;
use App\Listeners\HandleBackupReadyEvent;
use App\Models\BackupLog;
use App\Models\DataSource;
use App\Models\File;
use App\Services\BackupDestinationService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Tests\TestCase;

class BackupDeliveryResilienceTest extends TestCase
{
    use RefreshDatabase;

    private function makeBackupLog(BackupStatusEnum $status = BackupStatusEnum::backup_ready): BackupLog
    {
        $dataSource = DataSource::create([
            'name' => 'Test DB',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'testdb',
            'username' => 'root',
            'password' => 'secret',
            'is_active' => true,
        ]);

        return BackupLog::create([
            'data_source_id' => $dataSource->id,
            'status' => $status,
            'type' => BackupTypeEnum::manual,
        ]);
    }

    private function makeTempBackupFile(TemporaryDirectory $temp, string $contents = 'zip-bytes'): BackupFileDTO
    {
        $tempFile = $temp->path('testdb_20260704120000.zip');
        file_put_contents($tempFile, $contents);

        return new BackupFileDTO(temporaryDirectory: $temp, fullPath: $tempFile);
    }

    private function makeFailingDestination(string $destinationId): BackupDestinationInterface
    {
        return new class($destinationId) implements BackupDestinationInterface {
            public function __construct(private string $destinationId) {}

            public function store(BackupLog $backupLog, string $temporaryFilePath, string $filename, array $metadata = []): ?string
            {
                throw new Exception('Connection refused');
            }

            public function getDestinationId(): string
            {
                return $this->destinationId;
            }

            public function createFileRecord(BackupLog $backupLog, string $filename, string $path, int $sizeBytes, string $hash, array $metadata = []): ?File
            {
                return null;
            }

            public function deleteFileRecord(File $file): bool
            {
                return true;
            }

            public function download(File $file): null
            {
                return null;
            }

            public function isEnabled(BackupLog $backupLog): bool
            {
                return false; // endpoint is "down" — probes fail
            }
        };
    }

    private function seedDestinationTimelines(BackupLog $backupLog, array $destinationIds, string $tempFilePath): void
    {
        $backupLog->recordStatusChange(BackupStatusEnum::backup_ready, [
            'storing_phase_started' => now()->toISOString(),
            'total_destinations' => count($destinationIds),
            'destinations_list' => $destinationIds,
            'temp_file_path' => $tempFilePath,
        ]);

        foreach ($destinationIds as $destinationId) {
            $backupLog->timelines()->create([
                'status' => BackupStatusEnum::storing_to_destinations,
                'metadata' => [
                    'destination_id' => $destinationId,
                    'has_started' => false,
                    'queued_at' => now()->toISOString(),
                    'retries' => 0,
                ],
            ]);
        }
    }

    public function test_failed_delivery_schedules_retry_with_capped_backoff(): void
    {
        config()->set('database-backup.delivery.max_attempts', 5);
        config()->set('database-backup.delivery.retry_base_seconds', 60);
        config()->set('database-backup.delivery.retry_max_seconds', 90);

        Queue::fake();

        $backupLog = $this->makeBackupLog();
        $temp = TemporaryDirectory::make();
        $fileData = $this->makeTempBackupFile($temp);
        $this->seedDestinationTimelines($backupLog, ['stub_dest'], $fileData->fullPath);

        $service = $this->createStub(BackupDestinationService::class);
        $service->method('getDestination')->willReturn($this->makeFailingDestination('stub_dest'));

        (new StoreBackupToDestinationJob($backupLog->id, 'stub_dest', $fileData, [], 1))->handle($service);

        // retryCount 1 → next attempt is 2; delay = min(60 * 2^1, 90) = 90 (capped)
        Queue::assertPushed(StoreBackupToDestinationJob::class, function (StoreBackupToDestinationJob $job) {
            return $job->retryCount === 2 && $job->delay !== null;
        });

        $timeline = $backupLog->timelines()->destinationSpecific()->latest('created_at')->first();
        $this->assertTrue($timeline->metadata['retry_scheduled']);
        $this->assertSame(90, $timeline->metadata['delay_seconds']);

        $temp->delete();
    }

    public function test_delivery_fails_permanently_after_max_attempts_and_finalizes(): void
    {
        config()->set('database-backup.delivery.max_attempts', 2);

        Queue::fake();
        Event::fake([AllDestinationsProcessedEvent::class]);

        $backupLog = $this->makeBackupLog();
        $temp = TemporaryDirectory::make();
        $fileData = $this->makeTempBackupFile($temp);
        $this->seedDestinationTimelines($backupLog, ['stub_dest'], $fileData->fullPath);

        $service = $this->createStub(BackupDestinationService::class);
        $service->method('getDestination')->willReturn($this->makeFailingDestination('stub_dest'));

        // retryCount 1 with max_attempts 2 → this is the final attempt
        (new StoreBackupToDestinationJob($backupLog->id, 'stub_dest', $fileData, [], 1))->handle($service);

        Queue::assertNotPushed(StoreBackupToDestinationJob::class);

        Event::assertDispatched(AllDestinationsProcessedEvent::class, function (AllDestinationsProcessedEvent $e) {
            $result = $e->results->toCollection()->first();

            return $result->success === false && $result->destinationId === 'stub_dest';
        });

        $temp->delete();
    }

    public function test_fan_out_includes_destinations_whose_liveness_probe_fails(): void
    {
        Queue::fake();

        $backupLog = $this->makeBackupLog();
        $temp = TemporaryDirectory::make();
        $fileData = $this->makeTempBackupFile($temp);

        $service = $this->createStub(BackupDestinationService::class);
        $service->method('getDestinations')->willReturn([
            'down_dest' => $this->makeFailingDestination('down_dest'),
        ]);

        (new HandleBackupReadyEvent($service))->handle(new BackupReadyEvent($backupLog, $fileData));

        // isEnabled() returns false, but the down destination must still get a job
        Queue::assertPushed(StoreBackupToDestinationJob::class, function (StoreBackupToDestinationJob $job) {
            return $job->destinationId === 'down_dest';
        });

        $phaseTimeline = $backupLog->timelines()
            ->where('status', BackupStatusEnum::backup_ready)
            ->whereNotNull('metadata->destinations_list')
            ->first();
        $this->assertSame(['down_dest'], $phaseTimeline->metadata['destinations_list']);
        $this->assertSame($fileData->fullPath, $phaseTimeline->metadata['temp_file_path']);

        $temp->delete();
    }

    public function test_reconcile_finalizes_stuck_backup_when_temp_file_is_gone(): void
    {
        $backupLog = $this->makeBackupLog();
        $this->seedDestinationTimelines($backupLog, ['dead_dest'], '/nonexistent/backup.zip');

        // Age the backup and its destination timeline beyond the stuck threshold
        BackupLog::withoutTimestamps(fn() => BackupLog::whereKey($backupLog->id)->update(['updated_at' => now()->subHours(5)]));
        $backupLog->timelines()->update(['updated_at' => now()->subHours(5)]);

        $this->artisan('backups:reconcile')->assertExitCode(0);

        $backupLog->refresh();
        $this->assertSame(BackupStatusEnum::failed, $backupLog->status);

        $deadTimeline = $backupLog->timelines()->destinationSpecific()->latest('created_at')->first();
        $this->assertFalse($deadTimeline->metadata['success']);
        $this->assertStringContainsString('reconciled', $deadTimeline->metadata['error_message']);
    }

    public function test_reconcile_redispatches_delivery_when_temp_file_still_exists(): void
    {
        Queue::fake();

        $backupLog = $this->makeBackupLog();
        $temp = TemporaryDirectory::make();
        $fileData = $this->makeTempBackupFile($temp);
        $this->seedDestinationTimelines($backupLog, ['lost_dest'], $fileData->fullPath);

        BackupLog::withoutTimestamps(fn() => BackupLog::whereKey($backupLog->id)->update(['updated_at' => now()->subHours(5)]));
        $backupLog->timelines()->update(['updated_at' => now()->subHours(5)]);

        $this->artisan('backups:reconcile')->assertExitCode(0);

        Queue::assertPushed(StoreBackupToDestinationJob::class, function (StoreBackupToDestinationJob $job) use ($backupLog, $fileData) {
            return $job->backupLogId === $backupLog->id
                && $job->destinationId === 'lost_dest'
                && $job->fileData->fullPath === $fileData->fullPath;
        });

        // Status untouched — the resumed delivery will finalize it
        $this->assertSame(BackupStatusEnum::backup_ready, $backupLog->fresh()->status);

        $temp->delete();
    }

    public function test_reconcile_marks_stuck_running_backup_as_failed(): void
    {
        $backupLog = $this->makeBackupLog(BackupStatusEnum::running);
        BackupLog::withoutTimestamps(fn() => BackupLog::whereKey($backupLog->id)->update(['updated_at' => now()->subHours(5)]));

        $this->artisan('backups:reconcile')->assertExitCode(0);

        $this->assertSame(BackupStatusEnum::failed, $backupLog->fresh()->status);
    }

    public function test_reconcile_leaves_recent_backups_alone(): void
    {
        Queue::fake();

        $backupLog = $this->makeBackupLog();
        $temp = TemporaryDirectory::make();
        $fileData = $this->makeTempBackupFile($temp);
        $this->seedDestinationTimelines($backupLog, ['busy_dest'], $fileData->fullPath);

        $this->artisan('backups:reconcile')->assertExitCode(0);

        Queue::assertNothingPushed();
        $this->assertSame(BackupStatusEnum::backup_ready, $backupLog->fresh()->status);

        $temp->delete();
    }
}
