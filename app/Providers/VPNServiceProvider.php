<?php

namespace App\Providers;

use App\Services\VPNService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class VPNServiceProvider extends ServiceProvider implements DeferrableProvider {
	public function register(): void {
		$this->app->singleton(VPNService::class, function(Application $app) {
			return new VPNService;
		});
	}

	public function provides(): array {
		return [VPNService::class];
	}
}
