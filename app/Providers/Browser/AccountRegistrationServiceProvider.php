<?php

namespace App\Providers\Browser;

use App\Services\Browser\AccountRegistrationService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class AccountRegistrationServiceProvider extends ServiceProvider implements DeferrableProvider {
	public function register(): void {
		$this->app->bind(AccountRegistrationService::class, function(Application $app) {
			return new AccountRegistrationService(AccountRegistrationService::BASE_URL, AccountRegistrationService::TIMEOUT, AccountRegistrationService::CONNECT_TIMEOUT);
		});
	}

	public function provides(): array {
		return [AccountRegistrationService::class];
	}
}
