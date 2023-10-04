<?php

namespace App\Services\Browser;

use App\Enums\NationAnimal;
use App\Enums\NationColor;
use App\Enums\Resource;
use App\Enums\WarPolicy;
use App\Models\BotAccount;
use App\Services\Browser\AccountSimulation\AllianceSimulation;
use App\Services\Browser\AccountSimulation\CitySimulation;
use App\Services\Browser\AccountSimulation\NationSimulation;
use App\Services\Browser\AccountSimulation\WarSimulation;
use App\Services\BrowserService;
use Illuminate\Support\Str;

class AccountSimulationService extends BrowserService {
	use AllianceSimulation, CitySimulation, NationSimulation, WarSimulation, PoliticsAndWar;

	protected BotAccount $account;
	protected object $nation;

	public function __construct(string $base_url = '', int $timeout = 30, int $connection_timeout = 60) {
		parent::__construct($base_url, $timeout, $connection_timeout, null, $this->checkResearchSurvey(...));
	}

	public function setAccount(BotAccount $account) {
		$this->account = $account;
		$this->loadNationSimData();
	}

	public function simulateDailyLogin(): void {
		$this->ensureAnimalChanged();

		//$this->ensureAlliance();
		$this->visitWars();
		$this->clearNotifications();
		$all_cities_compliant = $this->visitCities();
		$this->buyMilitary();
		$this->buyMissingResources();
		if ($all_cities_compliant && $this->canBuyCity()) {
			$this->buyCity();
		}

		$this->startRaid();

		/*if ($this->nation->num_cities >= 5 && $all_cities_compliant) {
			$this->depositResources();
		}*/
	}

	public function simulatePlayerControlledLogin(): void {
		$this->visitRaidWars();
		$this->depositResources();
	}

	protected function loadNationSimData(): void {
		$this->nation = $this->account->getNationSimData();
	}

	public function buyMissingResources(): bool {
		if ($this->nation->{Resource::FOOD->value} < 1000) {
			if (!$this->buyResource(Resource::FOOD->value, 1000)) {
				return false;
			}
		}

		if ($this->nation->{Resource::URANIUM->value} < 25) {
			if (!$this->buyResource(Resource::URANIUM->value, 75)) {
				return false;
			}
		}

		return true;
	}

	public function buyResource(string $resource, int $amount): bool {
		$trade_page = $this->parseHTML($this->client->get('/index.php', [
			'id' => 26,
			'display' => 'world',
			'resource1' => $resource,
			'buysell' => 'sell',
			'ob' => 'price',
			'od' => 'DEF',
			'maximum' => 15,
			'minimum' => 0,
			'search' => 'Go'
		]));

		$buy_table = $trade_page->querySelector('#rightcolumn > table.nationtable');
		if (empty($buy_table)) {
			return false;
		}

		$buy_form = $buy_table->getElementsByTagName('form')[0] ?? null;
		if (empty($buy_form)) {
			return false;
		}

		$form = [];
		$inputs = $buy_form->getElementsByTagName('input');
		foreach ($inputs as $input) {
			$name = $input->getAttribute('name');
			if (empty($name)) {
				continue;
			}

			$form[$name] = $input->getAttribute('value') ?? '';
		}

		$form['rcustomamount'] = $amount;
		$buy_page = $this->parseHTML($this->client->asForm()->post('/index.php?id=26&display=world', $form));
		$success_div = $buy_page->querySelector('#rightcolumn > div.alert-success');
		if (!empty($success_div) && Str::contains(strtolower($success_div->getTextContent()), 'successfully accepted a trade offer')) {
			return true;
		}

		return false;
	}

	private function calcMinResources(float $infra, float $land, object $city): array {
		$cost = 250000.0; // At least this much on top of calculations
		$infra_to_buy = $infra - $city->infrastructure;
		$land_to_buy = $land - $city->land;
		if ($infra_to_buy > 0) {
			$cost += $this->calcInfraBuyCost(
				$infra,
				$city->infrastructure,
				boolval($this->nation->center_for_civil_engineering),
				boolval($this->nation->advanced_engineering_corps),
				boolval($this->nation->government_support_agency),
				strcasecmp($this->nation->domestic_policy, 'URBANIZATION') === 0
			);
		}

		if ($land_to_buy > 0) {
			$cost += $this->calcLandBuyCost(
				$land,
				$city->land,
				boolval($this->nation->arable_land_agency),
				boolval($this->nation->advanced_engineering_corps),
				boolval($this->nation->government_support_agency),
				strcasecmp($this->nation->domestic_policy, 'RAPID_EXPANSION') === 0
			);
		}

		$resources = $this->calcImprovementCosts($city);
		$resources[Resource::MONEY->value] += $cost;

		return $resources;
	}

	private function ensurePurple(): void {
		if (NationColor::fromNormalized($this->nation->color) === NationColor::PURPLE) {
			return;
		}

		$this->editNation('color', NationColor::PURPLE->getFormValue());
	}

	private function ensurePirate(): void {
		if (WarPolicy::fromNormalized($this->nation->war_policy) === WarPolicy::PIRATE) {
			return;
		}

		$this->editNation('warpolicy', WarPolicy::PIRATE->getFormValue());
	}

	private function ensureNotGray(): void {
		if (NationColor::fromNormalized($this->nation->color) !== NationColor::GRAY) {
			return;
		}

		$this->ensurePurple();
	}

	public function ensureAlliance(): bool {
		if ($this->nation->alliance_id != 0) {
			return true;
		}

		if (!$this->client->get('/alliance/id=' . static::ALLIANCE_ID)->successful()) {
			return false;
		}

		$join_page = $this->parseHTML($this->client->get('/alliance/join/id=' . static::ALLIANCE_ID));
		$join_form = $join_page?->querySelector('#rightcolumn form');
		if (empty($join_form)) {
			return false;
		}

		$form = [];
		$inputs = $join_form->getElementsByTagName('input');
		foreach ($inputs as $input) {
			$name = $input->getAttribute('name') ?? '';
			if (empty($name)) {
				continue;
			}

			$form[$name] = $input->getAttribute('value') ?? '';
		}

		$joined_page = $this->parseHTML($this->client->asForm()->post('/alliance/join/id=' . static::ALLIANCE_ID, $form));
		if (empty($join_page)) {
			return false;
		}

		$right_column = $joined_page->getElementById('rightcolumn');
		if (empty($right_column)) {
			return false;
		}

		$html = strtolower(trim($right_column->innerHTML));
		return Str::contains($html, 'successfully applied');
	}

	private function ensureAnimalChanged(): void {
		if ($this->account->changed_animal) {
			return;
		}

		if (!$this->editNation('national_animal', NationAnimal::getRandomAnimal()->getFormValue())) {
			return;
		}

		$this->account->changed_animal = true;
		$this->account->save();
	}
}
