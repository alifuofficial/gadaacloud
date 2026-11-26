<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

if (class_exists(\Workdo\Hrm\Console\ProcessDueSeparationsCommand::class)) {
    Schedule::command('hrm:process-separations')->dailyAt('00:05');
}
