<?php

namespace App\Console\Commands;

use App\Models\BotAccount;
use App\Services\Browser\AccountRegistrationService;
use App\Services\Browser\LoginService;
use App\Services\VPNService;
use Illuminate\Console\Command;

class FinishAccountRegistration extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'account:finish-registration {account}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Finish registering a new account';

	/**
	 * Execute the console command.
	 */
	public function handle(AccountRegistrationService $registrator, LoginService $login_service, VPNService $vpn_service): void {
		$account = BotAccount::where('id', $this->argument('account'))->first();
		if (empty($account)) {
			$this->error('No such account with id: ' . $this->argument('account'));
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
			if (!$account->nation_created) {
				if (!$registrator->resetPassword($account)) {
					$this->error('Unable to reset account password');
					return;
				}
			}

			if (!$login_service->login($account->email->login, $account->password)) {
				$this->error('Unable to login to account');
				return;
			}

			$registrator->setCookies($login_service->getCookies());
			if (!$account->verified) {
				if (!$registrator->verifyEmail($account->email->getMailHandler())) {
					$this->error('Unable to verify account email');
					return;
				}

				$account->verified = true;
				$account->save();
			}

			if (!$account->nation_created) {
				if (!$registrator->createNation($account)) {
					$this->error('Unable to create new account nation');
					return;
				}
			}

			if (!$account->tutorial_completed) {
				if ($registrator->completeTutorial()) {
					$account->tutorial_completed = true;
					$account->save();
				}
			}

			if (!$account->built_first_project) {
				if ($registrator->buyFirstProject()) {
					$account->built_first_project = true;
					$account->save();
				}
			}

			$this->info('Account registration successfully completed');
		} finally {
			$vpn_service->disconnect();
		}
	}
}
