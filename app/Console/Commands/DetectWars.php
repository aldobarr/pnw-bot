<?php

namespace App\Console\Commands;

use App\Models\BotAccount;
use App\Models\War;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class DetectWars extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'wars:detect';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Detects any new defensive war for monitored accounts';

	/**
	 * Execute the console command.
	 */
	public function handle(): void {
		$nation_ids = BotAccount::where('banned', false)->where('verified', true)->where('nation_created', true)->pluck('nation_id');
		foreach (War::where('active', true)->where('responded', false)->whereIn('defender_id', $nation_ids)->cursor() as $war) {
			if ($this->respondToWar($war)) {
				$war->responded = true;
				$war->save();
			}
		}
	}

	private function respondToWar(War $war): bool {
		$command = Process::path(base_path())->run('php artisan wars:respond ' . $war->id . ' --isolated=1');
		return $command->successful();
	}
}
