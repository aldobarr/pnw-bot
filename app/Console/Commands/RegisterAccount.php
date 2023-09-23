<?php

namespace App\Console\Commands;

use App\Enums\MailHandler;
use App\Mail\Handlers\Secmail;
use App\Models\BotAccount;
use App\Models\Email;
use App\Services\Browser\AccountRegistrationService;
use App\Services\VPNService;
use Illuminate\Console\Command;

class RegisterAccount extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'account:register {--tempmail : Whether the account should be registered using a temp mail service}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Register a new account';

	/**
	 * Execute the console command.
	 */
	public function handle(AccountRegistrationService $registrator, VPNService $vpn_service): void {
		if ($vpn_service->isConnected()) {
			$this->error('VPN already connected');
			return;
		}

		$vpn = $vpn_service->findServer(BotAccount::getUsedMap());
		$vpn_service->connect($vpn);
		if (!$vpn_service->isConnected($vpn)) {
			$this->error('Unable to connect to the VPN');
			return;
		}

		try {
			if ($this->option('tempmail')) {
				$username = $registrator->getRandomUsername() . random_int(10, 99);
				$domains = Secmail::getDomains();
				if (empty($domains)) {
					$this->error('No domains found from temp mail service');
					return;
				}

				$email = new Email;
				$email->login = $username . '@' . $domains[random_int(0, count($domains) - 1)];
				$email->password = '';
				$email->type = MailHandler::SECMAIL;
				$email->save();
			}

			if (!Email::areThereAnyUnusedEmails($this->option('tempmail'))) {
				$this->error('No free email addresses left for registration');
				return;
			}

			$this->registerAccount($registrator, $vpn);
		} finally {
			$vpn_service->disconnect();
		}
	}

	private function registerAccount(AccountRegistrationService $registrator, string $vpn): void {
		$account = $registrator->createAccount($vpn, $this->option('tempmail'));
		if (empty($account)) {
			$this->error('Failed to create a new bot account');
			return;
		}

		$this->info('New bot account with email address "' . $account->email->login . '" on VPN "' . $account->vpn . '" created.');
	}
}
