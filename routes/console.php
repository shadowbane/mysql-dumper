<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

\Illuminate\Support\Facades\Schedule::command('backups:dispatch-scheduled')
    ->everyMinute()
    ->onOneServer()
    ->timezone('UTC')
    ->withoutOverlapping();

\Illuminate\Support\Facades\Schedule::command('backups:cleanup --queue')
    ->daily()
    ->at('02:00')
    ->onOneServer()
    ->timezone('UTC')
    ->withoutOverlapping();
