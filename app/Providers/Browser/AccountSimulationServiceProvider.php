<?php

namespace App\Providers\Browser;

use App\Services\Browser\AccountSimulationService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class AccountSimulationServiceProvider extends ServiceProvider implements DeferrableProvider {
	public function register(): void {
		$this->app->singleton(AccountSimulationService::class, function(Application $app) {
			return new AccountSimulationService(AccountSimulationService::BASE_URL, AccountSimulationService::TIMEOUT, AccountSimulationService::CONNECT_TIMEOUT);
		});
	}

	public function provides(): array {
		return [AccountSimulationService::class];
	}
}
