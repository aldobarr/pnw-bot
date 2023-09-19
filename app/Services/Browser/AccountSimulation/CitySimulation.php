<?php

namespace App\Services\Browser\AccountSimulation;

use App\Enums\Continent;
use App\Enums\Improvement;
use App\Enums\Resource;
use App\Services\Browser\AccountRegistrationService;
use Illuminate\Support\Str;
use IvoPetkov\HTML5DOMDocument;

trait CitySimulation {
	public function visitCities(): bool {
		$cities_page = $this->parseHTML($this->client->get('/cities/'));
		$cities_table = $cities_page?->querySelector('#rightcolumn table.nationtable');
		if (empty($cities_table)) {
			return false;
		}

		$compliance = true;
		$reload_data = false;
		foreach ($this->nation->cities as $city) {
			if ($this->visitCity($city)) {
				$reload_data = true;
			}
		}

		if ($reload_data) {
			$this->loadNationSimData();
		}

		foreach ($this->nation->cities as $city) {
			if (!$this->isCityCompliant($city)) {
				$compliance = false;
			}
		}

		return $compliance;
	}

	private function visitCity(object $city): bool {
		if ($this->isCityCompliant($city)) {
			return false;
		}

		$city_page = $this->parseHTML($this->client->get('/city/id=' . $city->id));
		if (empty($city_page)) {
			return false;
		}

		$infra = $this->calcMinInfra($this->nation->num_cities);
		$land = $this->calcMinLand($this->nation->num_cities);
		$min_resources = $this->calcMinResources($infra, $land, $city);
		if ($this->nation->{Resource::MONEY->value} < $min_resources[Resource::MONEY->value]) {
			return false;
		}

		$reload_data = false;
		unset($min_resources[Resource::MONEY->value], $min_resources[Resource::CREDITS->value]);
		foreach ($min_resources as $resource => $amount) {
			if ($amount > $this->nation->{$resource}) {
				if (!$this->buyResource($resource, $amount)) {
					return $reload_data;
				} else {
					$reload_data = true;
				}
			}
		}

		if (!$this->buyInfraAndLand($infra - $city->infrastructure, $land - $city->land, $city, $city_page)) {
			return $reload_data;
		}

		$this->importImprovementTemplate($city->id);
		return true;
	}

	private function importImprovementTemplate(int $id): bool {
		$page = $this->parseHTML($this->client->get('/city/improvements/import/id=' . $id));
		if (empty($page)) {
			return false;
		}

		$form = [];
		$import_form = $page->querySelector('#rightcolumn form');
		$inputs = $import_form->getElementsByTagName('input');
		foreach ($inputs as $input) {
			$name = $input->getAttribute('name') ?? '';
			if (empty($name) || Str::contains(strtolower($name), 'preview')) {
				continue;
			}

			$form[$name] = $input->getAttribute('value') ?? '';
		}

		$form['imp_import'] = json_encode($this->getImprovementTemplate());
		$import = $this->parseHTML($this->client->asForm()->post('/city/improvements/import/id=' . $id, $form));
		$success_div = $import->querySelector('#rightcolumn div.alert-success');
		if (empty($success_div)) {
			return false;
		}

		$text = strtolower($success_div->getTextContent());
		return Str::contains($text, 'improvements have been');
	}

	private function getImprovementTemplate(): array {
		$continent_resources = Continent::from($this->nation->continent)->getAvailableRaws();
		$improvements = [
			'infra_needed' =>  1000,
			'imp_total' =>  20,
			'imp_coalpower' =>  0,
			'imp_oilpower' =>  0,
			'imp_windpower' =>  0,
			'imp_nuclearpower' =>  1,
			'imp_coalmine' =>  0,
			'imp_oilwell' =>  0,
			'imp_uramine' =>  0,
			'imp_leadmine' =>  0,
			'imp_ironmine' =>  0,
			'imp_bauxitemine' =>  0,
			'imp_farm' =>  0,
			'imp_gasrefinery' =>  0,
			'imp_aluminumrefinery' =>  0,
			'imp_munitionsfactory' =>  0,
			'imp_steelmill' =>  0,
			'imp_policestation' =>  0,
			'imp_hospital' =>  1,
			'imp_recyclingcenter' =>  0,
			'imp_subway' =>  1,
			'imp_supermarket' =>  0,
			'imp_bank' =>  0,
			'imp_mall' =>  0,
			'imp_stadium' =>  0,
			'imp_barracks' =>  5,
			'imp_factory' =>  1,
			'imp_hangars' =>  5,
			'imp_drydock' =>  0
		];

		$improvements_left = $improvements['imp_total'];
		foreach ($improvements as $key => $val) {
			if (strcmp($key, 'infra_needed') === 0 || strcmp($key, 'imp_total') === 0) {
				continue;
			}

			$improvements_left -= $val;
		}

		while ($improvements_left > 0 && !empty($continent_resources)) {
			$resource_key = array_key_first($continent_resources);
			$resource = $continent_resources[$resource_key];
			$improvement = $resource->improvement();
			if ($improvements[$improvement->value] > $improvement->limit()) {
				unset($continent_resources[$resource_key]);
				continue;
			}

			$improvements[$improvement->value]++;
			$improvements_left--;
		}

		if ($this->nation->num_cities > 10) {
			// add more
			$improvements['infra_needed'] = 1500;
			$improvements['imp_total'] = 20;
		}

		return $improvements;
	}

	public function isCityCompliant(object $city): bool {
		$infra = $this->calcMinInfra($this->nation->num_cities);
		$land = $this->calcMinLand($this->nation->num_cities);
		if ($infra > $city->infrastructure) {
			return false;
		}

		if ($land > $city->land) {
			return false;
		}

		$improvements = $this->getImprovementTemplate();
		unset($improvements['infra_needed'], $improvements['imp_total']);
		foreach ($improvements as $key => $value) {
			$improvement = Improvement::from($key);
			if ($city->{$improvement->apiKey()} < $value) {
				return false;
			}
		}

		return true;
	}

	public function buyInfraAndLand(float $infra, float $land, object $city, ?HTML5DOMDocument $city_page): bool {
		if ($infra <= 0 && $land <= 0) {
			return true;
		}

		if (empty($city_page)) {
			$city_page = $this->parseHTML($this->client->get('/city/id=' . $city->id));
			if (empty($city_page)) {
				return false;
			}
		}

		$infra_land_form = $city_page->getElementById('buyinfralandform');
		if (empty($infra_land_form)) {
			return false;
		}

		$form = [];
		$action = $infra_land_form->getAttribute('action');
		$inputs = $infra_land_form->getElementsByTagName('input');
		foreach ($inputs as $input) {
			$name = $input->getAttribute('name');
			if (empty($name)) {
				continue;
			}

			$form[$name] = $input->getAttribute('value') ?? '';
		}

		$form['infra'] = $infra ?: '';
		$form['land'] = $land ?: '';
		$buy_page = $this->parseHTML($this->client->asForm()->post($action, $form));
		$success = $buy_page->querySelector('#rightcolumn div.alert-success');
		if (empty($success)) {
			return false;
		}

		$text = strtolower($success->getTextContent());
		return Str::contains($text, 'successfully purchased') && (
			Str::contains($text, 'infrastructure') || Str::contains($text, 'land')
		);
	}

	public function canBuyCity(): bool {
		if ($this->nation->num_cities >= 5) {
			return false; // Temp
		}

		$city_cost = $this->calcCityBuyCost(
			$this->nation->num_cities + 1,
			boolval($this->nation->urban_planning),
			boolval($this->nation->advanced_urban_planning),
			boolval($this->nation->metropolitan_planning),
			boolval($this->nation->government_support_agency),
			strcasecmp($this->nation->domestic_policy, 'MANIFEST_DESTINY') === 0
		);

		return $this->nation->{Resource::MONEY->value} > ($city_cost + 1000000);
	}

	public function buyCity(): bool {
		$city_page = $this->parseHTML($this->client->get('/city/create/'));
		if (empty($city_page)) {
			return false;
		}

		$city_form = $city_page->querySelector('#rightcolumn form');
		if (empty($city_form)) {
			return false;
		}

		$form = [];
		$inputs = $city_form->getElementsByTagName('input');
		foreach ($inputs as $input) {
			$name = $input->getAttribute('name');
			if (empty($name)) {
				continue;
			}

			$form[$name] = $input->getAttribute('value') ?? '';
		}

		$form['newcityname'] = app(AccountRegistrationService::class)->getRandomCityName();
		$bought_page = $this->parseHTML($this->client->asForm()->post('/city/create/', $form));
		if (empty($bought_page)) {
			return false;
		}

		$success = $bought_page->querySelector('#rightcolumn p.alert-success');
		if (empty($success)) {
			return false;
		}

		$text = strtolower(trim($success->getTextContent()));
		if (Str::contains($text, 'successfully created a new city')) {
			$this->loadNationSimData();
			$this->visitCities();
			$this->buyMilitary();
			$this->buyMissingResources();
			return true;
		}

		return false;
	}

	private function calcMinInfra(int $city_count): int {
		if ($city_count < 10) {
			return 1000;
		} else if ($city_count < 15) {
			return 1500;
		}

		return 2000;
	}

	private function calcMinLand(int $city_count): int {
		if ($city_count < 10) {
			return 1000;
		} else if ($city_count < 15) {
			return 1500;
		}

		return 2000;
	}
}