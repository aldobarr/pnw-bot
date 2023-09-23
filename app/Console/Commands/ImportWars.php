<?php

namespace App\Console\Commands;

use App\Models\BotAccount;
use App\Services\PoliticsAndWarAPIService;
use App\Services\VPNService;
use Illuminate\Console\Command;

class ImportWars extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'import:wars {days_ago?}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Imports PnW war data';

	/**
	 * Execute the console command.
	 */
	public function handle(VPNService $vpn): void {
		$vpn->excludeCurrentProcess();

		try {
			$api_service = new PoliticsAndWarAPIService(BotAccount::getMainAccount()->api_key);
			$api_service->importWars($this->output->createProgressBar(), intval($this->argument('days_ago') ?? 1));
			$this->info(PHP_EOL . 'Process Complete');
		} catch (\Throwable $t) {
			$this->error($t->getMessage());
			throw $t;
		} finally {
			$vpn->restoreCurrentProcess();
		}
	}
}
