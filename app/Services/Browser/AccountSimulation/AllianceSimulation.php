<?php

namespace App\Services\Browser\AccountSimulation;

use App\Models\BotAccount;
use App\Services\Browser\AccountSimulationService;
use Illuminate\Support\Str;

trait AllianceSimulation {
	public function approveMembers(): void {
		$alliance_page = $this->parseHTML($this->client->get('/alliance/id=' . AccountSimulationService::ALLIANCE_ID . '&display=acp'));
		if (empty($alliance_page)) {
			return;
		}

		$tables = $alliance_page->querySelectorAll('#rightcolumn table.nationtable');
		foreach ($tables as $table) {
			$potential_applicants = $table->getElementsByTagName('a');
			foreach ($potential_applicants as $potential_applicant) {
				$url = $potential_applicant->getAttribute('href') ?? '';
				if (!Str::contains($url, 'alliance/id=' . AccountSimulationService::ALLIANCE_ID) || !Str::contains($url, 'action=1')) {
					continue;
				}

				$link_parts = [];
				parse_str(parse_url($url)['path'], $link_parts);
				if (empty($link_parts['appID']) || !BotAccount::where('nation_id', $link_parts['appID'])->exists()) {
					continue;
				}

				$this->client->get($url);
				sleep(random_int(1, 2));
			}
		}
	}
}