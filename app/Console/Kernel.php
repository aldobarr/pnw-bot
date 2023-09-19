<?php

namespace App\Console;

use App\Console\Commands\AccountLogin;
use App\Console\Commands\DetectWars;
use App\Console\Commands\ImportMarketData;
use App\Console\Commands\ImportNations;
use App\Console\Commands\ImportWars;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel {
	/**
	 * Define the application's command schedule.
	 */
	protected function schedule(Schedule $schedule): void {
		$schedule->command(AccountLogin::class)
				->everyMinute()
				->withoutOverlapping()
				->runInBackground();

		$schedule->command(ImportMarketData::class)->hourly()
				->withoutOverlapping()
				->runInBackground();

		$schedule->command(ImportNations::class)
				->hourly()
				->withoutOverlapping()
				->runInBackground();

		$schedule->command(ImportWars::class)
				->everyFifteenSeconds()
				->withoutOverlapping()
				->runInBackground();

		$schedule->command(ImportWars::class, [6])
				->everyThirtyMinutes()
				->withoutOverlapping()
				->runInBackground();

		$schedule->command(DetectWars::class)
				->everyTenSeconds()
				->withoutOverlapping()
				->runInBackground();
	}

	/**
	 * Register the commands for the application.
	 */
	protected function commands(): void {
		$this->load(__DIR__.'/Commands');

		require base_path('routes/console.php');
	}
}
