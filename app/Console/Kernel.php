<?php

namespace App\Console;

use App\Console\Commands\DetectIdleUsers;
use App\Console\Commands\MonitorHelpRequests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * @var array<int, class-string>
     */
    protected $commands = [
        DetectIdleUsers::class,
        MonitorHelpRequests::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('time:detect-idle')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('time:monitor-help')
            ->everyFiveMinutes()
            ->runInBackground();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}