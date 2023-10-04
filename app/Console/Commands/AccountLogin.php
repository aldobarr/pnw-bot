<?php

namespace App\Console\Commands;

use App\Models\BotAccount;
use App\Services\VPNService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AccountLogin extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'account:login {account?}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Logs in all users for daily rewards';

	/**
	 * Execute the console command.
	 */
	public function handle(VPNService $vpn): void {
		if ($this->detectMaintenace()) {
			$this->error('Turn maintenance detected');
			return;
		}

		if ($vpn->isConnected()) {
			$this->error('VPN already connected');
			return;
		}

		$account_id = $this->argument('account');
		if (empty($account_id)) {
			$this->doLogins();
			return;
		}

		$account = BotAccount::where('id', $account_id)->first();
		if (empty($account)) {
			$this->error('No such account with id: ' . $account_id);
			return;
		}

		if ($account->banned) {
			$this->error($account->email->login . ' is banned');
			return;
		}

		$account->login();
		$this->info('Process complete for account: ' . $account->email->login);
	}

	private function doLogins() {
		$query = BotAccount::loginQueue();
		foreach ($query->lazy() as $account) {
			$this->info('Performing login process for account: ' . $account->id);
			$account->login();
		}

		$this->info('Process complete');
	}

	private function detectMaintenace(): bool {
		$now = now();
		if ($now->hour === 0) {
			return $now->minute <= 10;
		}

		if (!($now->hour & 1)) {
			return $now->minute == 0;
		}

		return $now->minute == 59;
	}
}
