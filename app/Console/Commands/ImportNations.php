<?php

namespace App\Console\Commands;

use App\Models\BotAccount;
use App\Services\PoliticsAndWarAPIService;
use App\Services\VPNService;
use Illuminate\Console\Command;

class ImportNations extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'import:nations';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Imports PnW nation data';

	/**
	 * Execute the console command.
	 */
	public function handle(VPNService $vpn): void {
		$vpn->excludeCurrentProcess();

		try {
			$api_service = new PoliticsAndWarAPIService(BotAccount::getLily()->api_key);
			$api_service->importNations($this->output->createProgressBar());
			$this->info(PHP_EOL . 'Process Complete');
		} catch (\Throwable $t) {
			$this->error($t->getMessage());
			throw $t;
		} finally {
			$vpn->restoreCurrentProcess();
		}
	}
}
