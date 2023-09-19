<?php

namespace App\Services\Browser\AccountSimulation;

use App\Enums\MilitaryUnit;
use App\Enums\Resource;
use App\Enums\WarType;
use App\Models\War;
use App\Services\PoliticsAndWarAPIService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait WarSimulation {
	public function buyMilitary(): void {
		$barracks = $factories = $hangars = $drydocks = 0;
		foreach ($this->nation->cities as $city) {
			$barracks += $city->barracks;
			$hangars += $city->hangar;
			$factories += $city->factory;
			$drydocks += $city->drydock;
		}

		$reload_data = false;
		$multiplier = $this->nation->propaganda_bureau ? 1.1 : 1;
		$max_soldiers = intval(min(intval(round($this->nation->population * 0.15)), 3000 * $barracks));
		$soldiers_per_day = intval(round(1000 * $barracks * $multiplier));
		$max_tanks = 250 * $factories;
		$tanks_per_day = intval(round(50 * $factories * $multiplier));
		$max_planes = 15 * $hangars;
		$planes_per_day = intval(round(3 * $hangars * $multiplier));
		$max_ships = 5 * $drydocks;
		$ships_per_day = intval(round($drydocks * $multiplier));
		$max_spies = $this->nation->central_intelligence_agency ? 60 : 50;

		if ($this->nation->soldiers < $max_soldiers) {
			$reload_data = true;
			$this->buyMilitaryUnit(MilitaryUnit::SOLDIERS, $soldiers_per_day);
		} else if ($this->nation->soldiers > $max_soldiers) {
			$reload_data = true;
			$this->sellMilitaryUnit(MilitaryUnit::SOLDIERS, $max_soldiers);
		}

		if ($this->nation->tanks < $max_tanks) {
			$reload_data = true;
			$this->buyMilitaryUnit(MilitaryUnit::TANKS, $tanks_per_day);
		} else if ($this->nation->tanks > $max_tanks) {
			$reload_data = true;
			$this->sellMilitaryUnit(MilitaryUnit::TANKS, $max_tanks);
		}

		if ($this->nation->aircraft < $max_planes) {
			$reload_data = true;
			$this->buyMilitaryUnit(MilitaryUnit::AIRCRAFT, $planes_per_day);
		} else if ($this->nation->aircraft > $max_planes) {
			$reload_data = true;
			$this->sellMilitaryUnit(MilitaryUnit::AIRCRAFT, $max_planes);
		}

		if ($this->nation->ships < $max_ships) {
			$reload_data = true;
			$this->buyMilitaryUnit(MilitaryUnit::NAVY, $ships_per_day);
		} else if ($this->nation->ships > $max_ships) {
			$reload_data = true;
			$this->sellMilitaryUnit(MilitaryUnit::NAVY, $max_ships);
		}

		if ($this->nation->spies < $max_spies && $this->nation->spies_today < 2) {
			$reload_data = true;
			$this->buyMilitaryUnit(MilitaryUnit::SPIES, 2);
		}

		if ($reload_data) {
			$this->loadNationSimData();
		}
	}

	public function buyMilitaryUnit(MilitaryUnit $unit, int $amount, bool $buy_missing_resources = true): bool {
		if ($amount <= 0) {
			return true;
		}

		$resources_to_buy = [];
		$resource_prices = PoliticsAndWarAPIService::calculateResourceAveragePrices();
		$resource_cost = $unit->getPurchaseCost();
		$cost = $resource_cost[Resource::MONEY->value] * $amount;
		unset($resource_cost[Resource::MONEY->value]);
		foreach ($resource_cost as $resource => $resource_cost) {
			$resources_needed = $resource_cost * $amount;
			if ($this->nation->{$resource} < $resources_needed) {
				if ($buy_missing_resources) {
					$cost += $resource_prices[$resource] * ($resources_needed - intval($this->nation->{$resource}));
					$resources_to_buy[$resource] = $resources_needed - intval($this->nation->{$resource});
				} else {
					return false;
				}
			}
		}

		if ($cost >= $this->nation->{Resource::MONEY->value}) {
			return false;
		}

		if (!empty($resources_to_buy)) {
			foreach ($resources_to_buy as $resource => $amount) {
				if (!$this->buyResource($resource, $amount)) {
					return false;
				}
			}
		}

		$action = '/nation/military/' . $unit->value . '/';
		$buy_page = $this->parseHTML($this->client->get($action));
		$buy_form = $buy_page->querySelector('#rightcolumn form');
		if (empty($buy_form)) {
			return false;
		}

		$form = [];
		$inputs = $buy_form->getElementsByTagName('input');
		foreach ($inputs as $input) {
			$name = $input->getAttribute('name');
			if (empty($name) || (Str::contains($name, 'buy') && Str::endsWith($name, '-xs'))) {
				continue;
			}

			$form[$name] = $input->getAttribute('value') ?? '';
			if (strcasecmp($input->getAttribute('type') ?? '', 'text') === 0 && !Str::endsWith($name, '-xs')) {
				if (intval($form[$name]) > $amount) {
					$form[$name] = $amount;
				} else {
					$amount = intval($form[$name]);
				}
			}
		}

		if ($amount <= 0) {
			return true;
		}

		$bought_page = $this->parseHTML($this->client->asForm()->post($action, $form));
		if (empty($bought_page)) {
			return false;
		}

		$success = $bought_page->querySelector('#rightcolumn div.alert-success');
		if (empty($success)) {
			return false;
		}

		$text = strtolower(trim($success->getTextContent()));
		return Str::contains($text, 'order complete') || Str::contains($text, 'successfully');
	}

	public function sellMilitaryUnit(MilitaryUnit $unit, int $amount): bool {
		if ($amount < 0) {
			return true;
		}

		$action = '/nation/military/' . $unit->value . '/';
		$buy_page = $this->parseHTML($this->client->get($action));
		$buy_form = $buy_page->querySelector('#rightcolumn form');
		if (empty($buy_form)) {
			return false;
		}

		$form = [];
		$inputs = $buy_form->getElementsByTagName('input');
		foreach ($inputs as $input) {
			$name = $input->getAttribute('name');
			if (empty($name) || (Str::contains($name, 'buy') && Str::endsWith($name, '-xs'))) {
				continue;
			}

			$value = $input->getAttribute('value') ?? '';
			if (strcasecmp($input->getAttribute('type') ?? '', 'text') === 0 && !Str::endsWith($name, '-xs')) {
				$value = '@' . $amount;
			}

			$form[$name] = $value;
		}

		$sold_page = $this->parseHTML($this->client->asForm()->post($action, $form));
		if (empty($sold_page)) {
			return false;
		}

		$success = $sold_page->querySelector('#rightcolumn div.alert-success');
		if (empty($success)) {
			return false;
		}

		$text = strtolower(trim($success->getTextContent()));
		return Str::contains($text, 'order complete') || Str::contains($text, 'successfully');
	}

	public function visitRaidWars(): void {
		$wars = War::where('active', true)->where(function(Builder $query) {
			$query->where('attacker_id', $this->account->nation_id)
				->orWhere('defender_id', $this->account->nation_id);
		})->get();

		foreach ($wars as $war) {
			if ($this->isRaidWar($war)) {
				$this->visitRaid($war);
				$war->save();
			}
		}
	}

	public function visitWars(): void {
		$wars = War::where('active', true)->where(function(Builder $query) {
			$query->where('attacker_id', $this->account->nation_id)
				->orWhere('defender_id', $this->account->nation_id);
		})->get();

		foreach ($wars as $war) {
			$this->visitWar($war);
			$war->save();
		}
	}

	public function visitWar(War &$war): bool {
		if ($this->isRaidWar($war)) {
			return $this->visitRaid($war);
		}

		return false;
	}

	public function isRaidWar(War &$war): bool {
		return $this->account->nation->id === $war->attacker_id && $war->type === WarType::RAID;
	}

	public function visitRaid(War &$war, int $attempts_left = 6): bool {
		if ($war->attacker_action_points < 3) {
			return true;
		}

		if ($attempts_left <= 0) {
			return false;
		}

		$battle_page = $this->parseHTML($this->client->get('/nation/war/groundbattle/war=' . $war->id));
		$battle_form = $battle_page?->querySelector('#rightcolumn form');
		if (empty($battle_form)) {
			return false;
		}

		$form = $this->getFormInputs($battle_form);
		if (empty($form)) {
			return false;
		}

		unset($form['soldiersUseMunitions']);
		$form['attTanks'] = 0;
		$form['attack'] = '';

		$attack_result = $this->parseHTML($this->client->asForm()->post('/nation/war/groundbattle/war=' . $war->id, $form));
		if (empty($attack_result)) {
			return false;
		}

		$results_table_area = $attack_result->querySelector('#rightcolumn div.pw-table');
		if (empty($results_table_area)) {
			return false;
		}

		$table = $results_table_area->getElementsByTagName('table')[0] ?? null;
		if (empty($table)) {
			return false;
		}

		$header = $table->getElementsByTagName('thead')[0] ?? null;
		if (empty($header)) {
			return false;
		}

		if (!Str::contains(strtolower(trim($header->getTextContent())), 'ground battle results')) {
			return false;
		}

		$war->attacker_action_points = $war->attacker_action_points - 3;
		return $this->visitRaid($war, $attempts_left - 1);
	}

	public function startRaid(): void {
		$this->ensureNotGray();
		if (!$this->militaryReady() || $this->account->nation->getOffensiveSlots() <= 1) {
			return;
		}

		$this->ensurePurple();
		$this->ensurePirate();

		$max_attempts = 5;
		$targets = $this->account->nation?->getTargetsInRange()?->toArray();
		$slots = $this->account->nation->getOffensiveSlots();
		$same_network = $this->account->sameNetwork->pluck('id')->toArray();
		while ($max_attempts-- > 0 && !empty($targets) && $slots > 1) {
			$next_key = array_key_first($targets);
			$target = $targets[$next_key];
			unset($targets[$next_key]);

			if (
				$this->account->nation->offensiveWars()->where('defender_id', $target['id'])->exists() ||
				$this->account->nation->defensiveWars()->where('attacker_id', $target['id'])->exists()
			) {
				continue;
			}

			if (
				!empty($same_network) &&
				War::whereIn('attacker_id', $same_network)
					->where('defender_id', $target['id'])
					->where('active', true)
					->exists()
			) {
				continue;
			}

			if (!$this->declareWar($target['id'])) {
				break;
			}

			$slots--;
		}

		(new PoliticsAndWarAPIService($this->account->api_key))->getMyWars();
		$latest_wars = War::where('attacker_id', $this->account->nation_id)->where('active', true)->get();
		foreach ($latest_wars as $war) {
			if ($this->isRaidWar($war)) {
				$this->visitRaid($war);
				$war->save();
			}
		}
	}

	public function declareWar(int $target_id, WarType $type = WarType::RAID, string $reason = 'Raid'): bool {
		$war_page = $this->parseHTML($this->client->get('/nation/war/declare/id=' . $target_id));
		$war_form = $war_page?->querySelector('#rightcolumn form');
		if (empty($war_form)) {
			return false;
		}

		$form = $this->getFormInputs($war_form);
		if (empty($form)) {
			return false;
		}

		$form['declare'] = '';
		$form['reason'] = $reason;
		$form['war_type'] = $type->getFormValue();
		$results = $this->parseHTML($this->client->asForm()->post('/nation/war/declare/id=' . $target_id, $form));
		if (empty($results)) {
			return false;
		}

		$success = $results->querySelector('#rightcolumn div.pw-alert-green');
		if (empty($success)) {
			return false;
		}

		$text = strtolower(trim($success->getTextContent()));
		return Str::contains($text, 'declared war');
	}

	private function militaryReady(): bool {
		if ($this->nation->num_cities < 4) {
			return false;
		}

		/*if ($this->nation->alliance_id == 0) {
			return false;
		}*/

		$barracks = $hangars = 0;
		foreach ($this->nation->cities as $city) {
			$barracks += $city->barracks;
			$hangars += $city->hangar;
		}

		$min_soldiers = 3000 * $barracks * 0.35;
		$min_planes = 15 * $hangars * 0.3;
		return $this->nation->soldiers > $min_soldiers && $this->nation->aircraft > $min_planes;
	}
}
