<?php

namespace App\Services;

use App\Enums\Project;
use App\Models\BotAccount;

class ProjectService {
	private BotAccount $account;
	private object $nation;

	public function __construct(BotAccount $account, object $nation) {
		$this->account = $account;
		$this->nation = $nation;
	}

	public function getProjectBuildOrder(): array {
		return [
			Project::ACTIVITY_CENTER,
			Project::PROPAGANDA_BUREAU,
			Project::PIRATE_ECONOMY,
			Project::ADVANCED_PIRATE_ECONOMY
		];
	}

	public function getNextProject(): ?Project {
		$projects = $this->getProjectBuildOrder();
		foreach ($projects as $project) {
			if ($this->nation->{$project->value}) {
				return $project;
			}
		}

		return null;
	}
}
