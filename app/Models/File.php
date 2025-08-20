<?php

namespace App\Models;

use App\Models\Traits\HasUlid32;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    use HasUlid32;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'filename',
        'path',
        'disk',
        'label',
        'mime_type',
        'size_bytes',
        'fileable_type',
        'fileable_id',
        'is_public',
        'ordering',
        'hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordering' => 'integer',
            'size_bytes' => 'integer',
            'is_public' => 'boolean',
        ];
    }

    protected $appends = [
        'human_size',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Delete the file from storage when the model is force deleted
        static::forceDeleted(function ($file) {
            Storage::disk($file->disk)->delete($file->path);
        });
    }

    /**
     * Get the parent fileable model.
     *
     * @return MorphTo
     */
    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the URL for the file.
     *
     * @return string
     */
    public function getUrlAttribute(): string
    {
        if ($this->is_public) {
            return Storage::disk($this->disk)->url($this->path);
        }

        // Generate a temporary URL for private files
        return Storage::disk($this->disk)->temporaryUrl(
            $this->path,
            now()->addMinutes(30)
        );
    }

    /**
     * Get human-readable file size.
     *
     * @return string
     */
    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size_bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
