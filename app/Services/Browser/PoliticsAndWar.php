<?php

namespace App\Services\Browser;

use App\Enums\Improvement;
use App\Enums\LoginStatus;
use App\Enums\Resource;
use App\Services\PoliticsAndWarAPIService;
use Carbon\Carbon;
use IvoPetkov\HTML5DOMDocument;

trait PoliticsAndWar {
	public const TIMEOUT = 30;
	public const CONNECT_TIMEOUT = 60;
	public const DOMAIN = 'politicsandwar.com';
	public const BASE_URL = 'https://politicsandwar.com';
	public const HOST_NAME = 'politicsandwar.com';
	public const ALLIANCE_ID = 11966;

	public static function getLastResetTime(): Carbon {
		return Carbon::createFromTime(0, 0, 0, 'UTC');
	}

	public static function getNextResetTime(): Carbon {
		return Carbon::createFromTime(0, 0, 0, 'UTC')->addDay();
	}

	public function checkLoginState(?HTML5DOMDocument $page = null): LoginStatus {
		if (empty($page)) {
			$page = $this->parseHTML($this->client->get('/'));
		}

		$left_column = $page->getElementById('leftcolumn');
		if (empty($left_column)) {
			return LoginStatus::LOGGED_OUT;
		}

		$lists = $left_column->querySelectorAll('ul.sidebar');
		foreach ($lists as $list) {
			$anchors = $list->getElementsByTagName('a');
			foreach ($anchors as $a) {
				$href = $a->getAttribute('href');
				if (empty($href)) {
					continue;
				}

				$url = parse_url($href);
				if (empty($url) || empty($url['host']) || empty($url['path']) || strcasecmp($url['host'], static::DOMAIN) !== 0) {
					continue;
				}

				if (strcasecmp($url['path'], '/logout/') === 0) {
					return LoginStatus::LOGGED_IN;
				}
			}
		}

		return LoginStatus::LOGGED_OUT;
	}

	public function calcInfraBuyCost(float $amount, float $current, bool $has_ccep, bool $has_aec, bool $has_gsa, bool $urbanization): float {
		$cost = 0;
		$discount = $urbanization ? ($has_gsa ? 0.925 : 0.95) : 1;
		if ($has_ccep) {
			$discount -= 0.05;
		}

		if ($has_aec) {
			$discount -= 0.05;
		}

		$amount_to_buy = ($amount - $current) % 100;
		if ($amount_to_buy <= 0) {
			$amount_to_buy = 100;
		}

		while ($current < $amount) {
			$buy = round($amount_to_buy * (300 + ((max($current - 10, 20) ** 2.2)) / 710), 2);
			if ($amount_to_buy == 100) {
				$buy = round($buy);
			}

			$cost += $discount * $buy;
			$current += $amount_to_buy;
			$amount_to_buy = 100;
		}

		return round($cost, 1);
	}

	public function calcLandBuyCost(float $amount, float $current, bool $has_ala, bool $has_aec, bool $has_gsa, bool $rapid_expansion): float {
		$cost = 0;
		$discount = $rapid_expansion ? ($has_gsa ? 0.925 : 0.95) : 1;
		if ($has_ala) {
			$discount -= 0.05;
		}

		if ($has_aec) {
			$discount -= 0.05;
		}

		$amount_to_buy = ($amount - $current) % 500;
		if ($amount_to_buy <= 0) {
			$amount_to_buy = 500;
		}

		while ($current < $amount) {
			$buy = round($amount_to_buy * ((0.002 * ((abs($current - 20) ** 2))) + 50), 2);
			if ($amount_to_buy == 500) {
				$buy = round($buy);
			}

			$cost += $discount * $buy;
			$current += $amount_to_buy;
			$amount_to_buy = 500;
		}

		return $cost;
	}

	public function calcCityBuyCost(int $city, bool $has_up, bool $has_aup, bool $has_mp, bool $has_gsa, bool $manifest_destiny): float {
		if ($city < 2) {
			return 0;
		}

		$discount = $manifest_destiny ? ($has_gsa ? 0.925 : 0.95) : 1;
		$cost = $discount * (50000 * (($city - 2) ** 3) + 150000 * ($city - 1) + 75000);

		if ($has_up) {
			$cost -= 50000000;
		}

		if ($has_aup) {
			$cost -= 100000000;
		}

		if ($has_mp) {
			$cost -= 150000000;
		}

		return round($cost, 2);
	}

	public function calcImprovementCosts(object $city, bool $include_resource_monetary_cost = true): array {
		$costs = [];
		$types = Resource::all();
		foreach ($types as $resource) {
			$costs[$resource] = 0;
		}

		$resource_prices = $include_resource_monetary_cost ? PoliticsAndWarAPIService::calculateResourceAveragePrices() : [];
		$improvements = $this->getImprovementTemplate();
		unset($improvements['infra_needed'], $improvements['imp_total']);
		foreach ($improvements as $imp_key => $amount) {
			$improvement = Improvement::from($imp_key);
			if ($amount === 0 || $amount <= $city->{$improvement->apiKey()}) {
				continue;
			}

			$imp_costs = $improvement->cost();
			foreach ($imp_costs as $resource => $amt) {
				$costs[$resource] += ($amount - $city->{$improvement->apiKey()}) * $amt;
				if ($include_resource_monetary_cost && strcmp($resource, Resource::MONEY->value) !== 0) {
					$costs[Resource::MONEY->value] += $costs[$resource] * $resource_prices[$resource];
				}
			}
		}

		return $costs;
	}
}
