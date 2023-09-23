<?php

namespace App\Console\Commands;

use App\Enums\Resource;
use App\Models\BotAccount;
use App\Models\Nation;
use App\Services\PoliticsAndWarAPIService;
use App\Services\VPNService;
use Illuminate\Console\Command;

class AllianceBank extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'alliance:bank';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Get alliance bank data';

	/**
	 * Execute the console command.
	 */
	public function handle(VPNService $vpn): void {
		$trixie = BotAccount::getMainAccount();
		if ($vpn->isConnected()) {
			$this->error('Unable to check alliance bank at the moment due to established VPN Connection');
			return;
		}

		$vpn->connect($trixie->vpn);
		if (!$vpn->isConnected($trixie->vpn)) {
			$this->error('Unable to establish a VPN Connection');
			return;
		}

		try {
			$resources = (new PoliticsAndWarAPIService($trixie->api_key))->getAllianceResources();
			if (empty($resources)) {
				$this->error('Unable to fetch resources');
				return;
			}

			$money = $resources[Resource::MONEY->value];
			$averages = PoliticsAndWarAPIService::calculateResourceAveragePrices();
			$this->info('Money: $' . number_format($resources[Resource::MONEY->value], 2));
			unset($resources[Resource::MONEY->value]);

			foreach ($resources as $resource => $amount) {
				$money += $amount * $averages[$resource];
				$this->info(ucfirst($resource) . ': ' . number_format($amount, 2));
			}

			$this->info(PHP_EOL . 'Estimated Bank Worth: $' . number_format(round($money, 2), 2));
		} finally {
			$vpn->disconnect();
		}
	}
}
