<?php

namespace App\Console\Commands;

use App\Exceptions\LoginException;
use App\Models\BotAccount;
use App\Models\War;
use App\Services\Browser\AccountSimulationService;
use App\Services\Browser\LoginService;
use App\Services\VPNService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;

class RespondToWar extends Command implements Isolatable {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'wars:respond {war}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Responds to a given war id';

	/**
	 * Execute the console command.
	 */
	public function handle(VPNService $vpn, LoginService $login_service, AccountSimulationService $simulator): void {
		$war_id = $this->argument('war');
		$war = War::find($war_id);
		if (empty($war)) {
			$this->throwError('Invalid war: ' . $war_id);
		}

		$account = BotAccount::where('banned', false)->where('nation_id', $war->defender_id)->first();
		if (empty($account)) {
			$this->throwError('Invalid defender: ' . $war->defender_id);
		}

		$max_wait = now()->addMinutes(30);
		while ($vpn->isConnected() && $max_wait->greaterThan(now())) {
			sleep(1);
		}

		if ($vpn->isConnected()) {
			$this->throwError('Unable to establish VPN connection');
		}

		$vpn->connect($account->vpn);
		if (!$vpn->isConnected($account->vpn)) {
			$this->throwError('Unable to establish VPN connection');
		}

		try {
			$this->respondToWar($account, $war, $login_service, $simulator);
		} catch (\Throwable $t) {
			$this->throwError($t->getMessage() ?? 'Unknown Exception', $t->getCode() !== 0 ? $t->getCode() : 1);
		} finally {
			$vpn->disconnect();
		}
	}

	private function respondToWar(BotAccount $account, War $war, LoginService $login_service, AccountSimulationService $simulator): void {
		if (!$login_service->login($account->email->login, $account->password)) {
			throw new LoginException('Unable to login to account', 2);
		}

		$simulator->setCookies($login_service->getCookies());
		$simulator->setAccount($account);
		if (!$simulator->visitWar($war)) {
			throw new \RuntimeException('Unable to perform war actions', 3);
		}
	}

	private function throwError(string $message, int $code = 1): void {
		$this->error($message);
		throw new \RuntimeException($message, $code);
	}
}
