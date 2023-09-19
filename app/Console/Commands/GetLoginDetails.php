<?php

namespace App\Console\Commands;

use App\Models\BotAccount;
use Illuminate\Console\Command;

class GetLoginDetails extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'account:info {account}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Get login details for account';

	/**
	 * Execute the console command.
	 */
	public function handle(): void {
		$account = BotAccount::where('id', $this->argument('account'))->first();
		if (empty($account)) {
			$this->error('No such account with id: ' . $this->argument('account'));
			return;
		}

		$this->info('Email: ' . $account->email->login);
		$this->info('Password: ' . $account->password);
		$this->info('VPN: ' . $account->vpn);
	}
}
