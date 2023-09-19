<?php

namespace App\Providers\Browser;

use App\Services\Browser\CaptchaSolver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class CaptchaSolverProvider extends ServiceProvider implements DeferrableProvider {
	public function register(): void {
		$this->app->singleton(CaptchaSolver::class, function(Application $app) {
			return new CaptchaSolver;
		});
	}

	public function provides(): array {
		return [CaptchaSolver::class];
	}
}
