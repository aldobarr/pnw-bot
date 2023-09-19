<?php

namespace App\Console\Commands;

use App\Models\BotAccount;
use App\Services\Browser\AccountRegistrationService;
use App\Services\VPNService;
use Illuminate\Console\Command;

class PasswordReset extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'account:reset-password {account}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Resets an account\'s password';

	/**
	 * Execute the console command.
	 */
	public function handle(AccountRegistrationService $registrator, VPNService $vpn_service): void {
		$account = BotAccount::where('id', $this->argument('account'))->first();
		if (empty($account)) {
			$this->error('No such account with id: ' . $this->argument('account'));
			return;
		}

		if ($account->banned) {
			$this->error($account->email->login . ' is banned');
			return;
		}

		if ($vpn_service->isConnected()) {
			$this->error('VPN already connected');
			return;
		}

		$vpn_service->connect($account->vpn);
		if (!$vpn_service->isConnected($account->vpn)) {
			$this->error('Unable to connect to the VPN');
			return;
		}

		try {
			if ($registrator->resetPassword($account)) {
				$this->info('Password successfully reset.');
			} else {
				$this->info('Failed to reset password.');
			}
		} finally {
			$vpn_service->disconnect();
		}
	}
}
