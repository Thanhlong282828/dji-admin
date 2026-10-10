<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('operations:heartbeat')->everyMinute()->onOneServer()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=336')->dailyAt('02:10')->onOneServer()->withoutOverlapping();
Schedule::command('queue:prune-batches --hours=168 --unfinished=168 --cancelled=168')->dailyAt('02:20')->onOneServer()->withoutOverlapping();
