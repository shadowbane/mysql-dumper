<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\Activitylog\LogOptions;

/**
 * Trait LogsActivity.
 */
trait LogsActivity
{
    use \Spatie\Activitylog\Traits\LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        $parts = explode('\\', static::class);

        return LogOptions::defaults()
            ->logAll()
            ->useLogName(end($parts))
            ->dontSubmitEmptyLogs();
    }

    /**
     * Always shows latest log first.
     *
     * @throws \Spatie\Activitylog\Exceptions\InvalidConfiguration
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(ActivitylogServiceProvider::determineActivityModel(), 'subject')
            ->latest();
    }
}
