<?php

namespace Tests\Feature;

use App\DTO\BackupFileDTO;
use App\DTO\DestinationResultDTO;
use App\Enums\BackupStatusEnum;
use App\Enums\BackupTypeEnum;
use App\Events\AllDestinationsProcessedEvent;
use App\Events\BackupCompletedEvent;
use App\Listeners\HandleAllDestinationsProcessedEvent;
use App\Models\BackupLog;
use App\Models\DataSource;
use App\Services\Destinations\LocalBackupDestination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelData\DataCollection;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Tests\TestCase;

class BackupDestinationStoreTest extends TestCase
{
    use RefreshDatabase;

    private function makeBackupLog(): BackupLog
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
            'status' => BackupStatusEnum::pending,
            'type' => BackupTypeEnum::manual,
        ]);
    }

    public function test_local_destination_streams_file_to_disk_and_creates_file_record(): void
    {
        Storage::fake('local');

        $backupLog = $this->makeBackupLog();

        $temp = TemporaryDirectory::make();
        $tempFile = $temp->path('testdb_20260704120000.zip');
        file_put_contents($tempFile, str_repeat('backup-bytes', 1000));

        $destination = new LocalBackupDestination('local', 'database-backups');

        $finalPath = $destination->store($backupLog, $tempFile, basename($tempFile));

        $this->assertSame('database-backups/testdb_20260704120000.zip', $finalPath);
        Storage::disk('local')->assertExists($finalPath);
        $this->assertSame(
            hash_file('md5', $tempFile),
            md5(Storage::disk('local')->get($finalPath)),
        );

        $file = $backupLog->files()->first();
        $this->assertNotNull($file);
        $this->assertSame(filesize($tempFile), (int) $file->size_bytes);
        $this->assertSame(hash_file('md5', $tempFile), $file->hash);

        $temp->delete();
    }

    public function test_completed_event_carries_real_filename_and_size(): void
    {
        Event::fake([BackupCompletedEvent::class]);

        $backupLog = $this->makeBackupLog();

        $temp = TemporaryDirectory::make();
        $tempFile = $temp->path('testdb_20260704120000.zip');
        file_put_contents($tempFile, str_repeat('x', 4096));

        $fileData = new BackupFileDTO(temporaryDirectory: $temp, fullPath: $tempFile);

        $event = new AllDestinationsProcessedEvent($backupLog, $fileData, new DataCollection(DestinationResultDTO::class, [
            new DestinationResultDTO(
                destinationId: 'local_local',
                success: true,
                filePath: 'database-backups/testdb_20260704120000.zip',
            ),
        ]));

        (new HandleAllDestinationsProcessedEvent)->handle($event);

        Event::assertDispatched(BackupCompletedEvent::class, function (BackupCompletedEvent $e) {
            return $e->filename === 'testdb_20260704120000.zip'
                && $e->fileSize === 4096;
        });

        $this->assertSame(BackupStatusEnum::completed, $backupLog->fresh()->status);
        $this->assertFileDoesNotExist($tempFile);

        $temp->delete();
    }
}
