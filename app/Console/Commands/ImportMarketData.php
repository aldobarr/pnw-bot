<?php

namespace App\Console\Commands;

use App\Enums\Resource;
use App\Models\BotAccount;
use App\Models\MarketSnapshot;
use App\Services\PoliticsAndWarAPIService;
use App\Services\VPNService;
use Illuminate\Console\Command;

class ImportMarketData extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'import:market';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Get PNW Market Data';

	/**
	 * Execute the console command.
	 */
	public function handle(VPNService $vpn): void {
		$vpn->excludeCurrentProcess();

		try {
			$this->purge();
			$time = now();
			$inserts = [];
			$resources = Resource::all(Resource::MONEY);
			$api_service = new PoliticsAndWarAPIService(BotAccount::getMainAccount()->api_key);

			foreach ($resources as $resource) {
				$data = $api_service->getMarketData($resource);
				if (empty($data)) {
					$this->error('Unable to get data for: ' . $resource);
					return;
				}

				$inserts[] = ['resource' => $resource, 'high_buy' => $data->highestbuy->price, 'low_buy' => $data->lowestbuy->price, 'avg' => $data->avgprice, 'imported_at' => $time];
			}

			MarketSnapshot::insert($inserts);
		} finally {
			$vpn->restoreCurrentProcess();
		}
	}

	private function purge(): void {
		MarketSnapshot::where('imported_at', '<', now()->subDays(45))->delete();
	}
}
