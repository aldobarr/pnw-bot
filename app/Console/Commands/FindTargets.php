<?php

namespace App\Console\Commands;

use App\Models\Nation;
use Illuminate\Console\Command;

class FindTargets extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'nation:targets {nation_id}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Find valid targets in range for a nation';

	/**
	 * Execute the console command.
	 */
	public function handle(): void {
		$nation = Nation::where('id', $this->argument('nation_id'))->first();
		if (empty($nation)) {
			$this->error('Invalid nation id supplied');
			return;
		}

		$target_nations = $nation->getTargetsInRange(false);
		foreach ($target_nations as $nation) {
			$loot = '$' . number_format($nation->last_loot_value * 0.01, 2);
			$this->info('Nation: ' . $nation->id . ' - Score: ' . number_format($nation->data->score, 2) . ' - Alliance: ' . $nation->data->alliance_id . ' - Color: ' . $nation->data->color . ' - Soldiers: ' . $nation->data->soldiers . ' - Tanks: ' . $nation->data->tanks . ' - Last Loot: ' . $loot);
		}
	}
}
