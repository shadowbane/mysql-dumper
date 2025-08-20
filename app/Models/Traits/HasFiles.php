<?php

namespace App\Models\Traits;

use App\Models\File;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HasFiles
{
    /**
     * Get all files for this model.
     */
    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable');
    }

    /**
     * Store a file and attach it to this model.
     *
     * @param  UploadedFile  $uploadedFile  The uploaded file
     * @param  string  $disk  Filesystem
     * @param  string  $path  Optional subdirectory path
     * @param  string|null  $label
     * @param  int  $ordering
     *
     * @throws \Exception
     *
     * @return File
     */
    public function storeFile(UploadedFile $uploadedFile, string $disk, string $path = '', ?string $label = null, int $ordering = 0): File
    {
        // Validate storage availability, but bypass for uploading file with specific label
        if (! in_array($label, ['logo']) && ! $this->validateStorageAvailability()) {
            throw new \Exception('You don\'t have any disk space left. Please consider upgrading.');
        }

        // Generate a unique filename
        $filename = $uploadedFile->getClientOriginalName();
        $extension = $uploadedFile->getClientOriginalExtension();
        $storagePath = trim($path, '/').'/'.md5(uniqid()).'-'.time().'.'.$extension;

        // Store file in filesystem
        $uploadedFile->storeAs('', $storagePath, ['disk' => $disk]);

        // Create file record
        return $this->files()->create([
            'filename' => $filename,
            'path' => $storagePath,
            'disk' => $disk,
            'label' => $label,
            'mime_type' => $uploadedFile->getMimeType(),
            'size_bytes' => $uploadedFile->getSize(),
            'ordering' => $ordering,
            'hash' => hash_file('md5', $uploadedFile->getRealPath()),
        ]);
    }

    private function validateStorageAvailability(): bool
    {
        $value = \App\Models\File::calculateDiskUsage();

        return ! ($value->bytes > $value->usage_limit);
    }

    /**
     * Delete a file by ID.
     *
     * @param  string  $fileId
     * @param  bool  $forceDelete  Whether to permanently delete
     * @param  bool  $deleteFile
     * @return bool
     */
    public function deleteFile(string $fileId, bool $forceDelete = false, bool $deleteFile = false): bool
    {
        $file = $this->files()->find($fileId);

        if (! $file) {
            return false;
        }

        // Also delete file
        if ($deleteFile) {
            if (Storage::disk($file->disk)->exists($file->path)) {
                Storage::disk($file->disk)->delete($file->path);
            }
        }

        if ($forceDelete) {
            return $file->forceDelete();
        }

        return $file->delete();
    }

    /**
     * Delete files for this model.
     *
     * @param  bool  $forceDelete
     * @param  bool  $deleteFile
     * @return bool
     */
    public function deleteFiles(bool $forceDelete = false, bool $deleteFile = false): bool
    {
        $files = $this->files()->get();
        $result = false;

        foreach ($files as $file) {
            // Also delete file
            if ($deleteFile) {
                if (Storage::disk($file->disk)->exists($file->path)) {
                    Storage::disk($file->disk)->delete($file->path);
                }
            }

            if ($forceDelete) {
                $result = $file->forceDelete();
            }
        }

        return $result;
    }
}
