<?php

namespace App\Events;

use App\Models\DataSource;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BackupRequested
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DataSource $dataSource
    ) {}
}
