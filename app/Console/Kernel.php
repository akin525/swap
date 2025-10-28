<?php

namespace App\Console;

use App\Jobs\AutoMatchingJob;
use App\Jobs\ReturnedEnergyJob;
use App\Models\GeneralSettings;
use Illuminate\Support\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('app:update-order-time')->everyMinute(); // or hourly, depending on needs

        $schedule->call(function () {
            AutoMatchingJob::dispatch();
            ReturnedEnergyJob::dispatch();
            // $setting = GeneralSettings::first();
            // if (!$setting) return;
        
            // $now = Carbon::now();
            // $closingTime = Carbon::parse($setting->closing_time);
        
            // Only dispatch job if current time is near the closing time
            // if ($now->greaterThanOrEqualTo($closingTime) && $now->diffInMinutes($closingTime) < 2) {
            //     AutoMatchingJob::dispatch();
            //     // \Log::info("Dispatched AutoMatchingJob job at {$now}");
            // }
        })->everyMinute();

        // $schedule->call(function () {
        //     $setting = GeneralSettings::first();
        //     if (!$setting) return;
    
        //     $now = Carbon::now();
        //     $closingTime = Carbon::parse($setting->closing_time);
    
        //     // Run only if now is equal or slightly past the closing time (within a 1-minute window)
        //     if ($now->greaterThanOrEqualTo($closingTime) && $now->diffInMinutes($closingTime) < 2) {
        //         \Artisan::call('app:peering-command');
        //         // \Log::info("Triggered pending-order-command at {$now}");
        //     }
        // })->everyMinute();
        
        // $schedule->command('samji:settlement')
        //     ->withoutOverlapping()
        //     ->dailyAt('00:30');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
