<?php

namespace App\Providers\Browser;

use App\Services\Browser\LoginService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class LoginServiceProvider extends ServiceProvider implements DeferrableProvider {
	public function register(): void {
		$this->app->singleton(LoginService::class, function(Application $app) {
			return new LoginService(LoginService::BASE_URL, LoginService::TIMEOUT, LoginService::CONNECT_TIMEOUT);
		});
	}

	public function provides(): array {
		return [LoginService::class];
	}
}
