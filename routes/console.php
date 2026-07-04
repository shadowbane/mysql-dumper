<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backups:dispatch-scheduled')
    ->everyMinute()
    ->onOneServer()
    ->timezone('UTC')
    ->withoutOverlapping();

Schedule::command('backups:cleanup --queue')
    ->daily()
    ->at('02:00')
    ->onOneServer()
    ->timezone('UTC')
    ->withoutOverlapping();

Schedule::command('backups:reconcile')
    ->everyFifteenMinutes()
    ->onOneServer()
    ->timezone('UTC')
    ->withoutOverlapping();
