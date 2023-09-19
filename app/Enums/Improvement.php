<?php

namespace App\Enums;

enum Improvement: string {
	case COAL_POWER = 'imp_coalpower';
	case OIL_POWER = 'imp_oilpower';
	case NUCLEAR_POWER = 'imp_nuclearpower';
	case WIND_POWER = 'imp_windpower';

	case COAL_MINE = 'imp_coalmine';
	case OIL_WELL = 'imp_oilwell';
	case IRON_MINE = 'imp_ironmine';
	case BAUXITE_MINE = 'imp_bauxitemine';
	CASE LEAD_MINE = 'imp_leadmine';
	CASE URANIUM_MINE = 'imp_uramine';
	case FARM = 'imp_farm';

	case OIL_REFINERY = 'imp_gasrefinery';
	case STEEL_MILL = 'imp_steelmill';
	case ALUMINUM_REFINERY = 'imp_aluminumrefinery';
	case MUNITIONS_FACTORY = 'imp_munitionsfactory';

	case POLICE_STATION = 'imp_policestation';
	CASE HOSPITAL = 'imp_hospital';
	case RECYCLING_CENTER = 'imp_recyclingcenter';
	case SUBWAY = 'imp_subway';

	CASE SUPER_MARKET = 'imp_supermarket';
	case BANK = 'imp_bank';
	case SHOPPING_MALL = 'imp_mall';
	case STADIUM = 'imp_stadium';

	CASE BARRACKS = 'imp_barracks';
	case FACTORY = 'imp_factory';
	case HANGAR = 'imp_hangars';
	case DRYDOCK = 'imp_drydock';

	case NONE = '';

	public function limit(): int {
		return match($this) {
			static::COAL_POWER => PHP_INT_MAX,
			static::OIL_POWER => PHP_INT_MAX,
			static::NUCLEAR_POWER => PHP_INT_MAX,
			static::WIND_POWER => PHP_INT_MAX,
			static::COAL_MINE => 10,
			static::OIL_WELL => 10,
			static::IRON_MINE => 10,
			static::BAUXITE_MINE => 10,
			static::LEAD_MINE => 10,
			static::URANIUM_MINE => 5,
			static::FARM => 20,
			static::OIL_REFINERY => 5,
			static::STEEL_MILL => 5,
			static::ALUMINUM_REFINERY => 5,
			static::MUNITIONS_FACTORY => 5,
			static::POLICE_STATION => 5,
			static::HOSPITAL => 5,
			static::RECYCLING_CENTER => 3,
			static::SUBWAY => 1,
			static::SUPER_MARKET => 6,
			static::BANK => 5,
			static::SHOPPING_MALL => 4,
			static::STADIUM => 3,
			static::BARRACKS => 5,
			static::FACTORY => 5,
			static::HANGAR => 5,
			static::DRYDOCK => 3,
			default => 0
		};
	}

	public function cost(): array {
		return match($this) {
			static::COAL_POWER => [Resource::MONEY->value => 5000],
			static::OIL_POWER => [Resource::MONEY->value => 7000],
			static::NUCLEAR_POWER => [Resource::MONEY->value => 500000, Resource::STEEL->value => 100],
			static::WIND_POWER => [Resource::MONEY->value => 30000, Resource::ALUMINUM->value => 30],
			static::COAL_MINE => [Resource::MONEY->value => 1000],
			static::OIL_WELL => [Resource::MONEY->value => 1500],
			static::IRON_MINE => [Resource::MONEY->value => 9500],
			static::BAUXITE_MINE => [Resource::MONEY->value => 9500],
			static::LEAD_MINE => [Resource::MONEY->value => 7500],
			static::URANIUM_MINE => [Resource::MONEY->value => 25000],
			static::FARM => [Resource::MONEY->value => 1000],
			static::OIL_REFINERY => [Resource::MONEY->value => 45000],
			static::STEEL_MILL => [Resource::MONEY->value => 45000],
			static::ALUMINUM_REFINERY => [Resource::MONEY->value => 30000],
			static::MUNITIONS_FACTORY => [Resource::MONEY->value => 35000],
			static::POLICE_STATION => [Resource::MONEY->value => 75000, Resource::STEEL->value => 20],
			static::HOSPITAL => [Resource::MONEY->value => 100000, Resource::ALUMINUM->value => 25],
			static::RECYCLING_CENTER => [Resource::MONEY->value => 125000],
			static::SUBWAY => [Resource::MONEY->value => 250000, Resource::STEEL->value => 50, Resource::ALUMINUM->value => 25],
			static::SUPER_MARKET => [Resource::MONEY->value => 5000],
			static::BANK => [Resource::MONEY->value => 15000, Resource::STEEL->value => 5, Resource::ALUMINUM->value => 10],
			static::SHOPPING_MALL => [Resource::MONEY->value => 45000, Resource::STEEL->value => 20, Resource::ALUMINUM->value => 25],
			static::STADIUM => [Resource::MONEY->value => 100000, Resource::STEEL->value => 40, Resource::ALUMINUM->value => 50],
			static::BARRACKS => [Resource::MONEY->value => 1000],
			static::FACTORY => [Resource::MONEY->value => 15000, Resource::ALUMINUM->value => 5],
			static::HANGAR => [Resource::MONEY->value => 100000, Resource::STEEL->value => 10],
			static::DRYDOCK => [Resource::MONEY->value => 250000, Resource::ALUMINUM->value => 20],
			default => []
		};
	}

	public function apiKey(): string {
		return match($this) {
			static::COAL_POWER => 'coal_power',
			static::OIL_POWER => 'oil_power',
			static::NUCLEAR_POWER => 'nuclear_power',
			static::WIND_POWER => 'wind_power',
			static::COAL_MINE => 'coal_mine',
			static::OIL_WELL => 'oil_well',
			static::IRON_MINE => 'iron_mine',
			static::BAUXITE_MINE => 'bauxite_mine',
			static::LEAD_MINE => 'lead_mine',
			static::URANIUM_MINE => 'uranium_mine',
			static::FARM => 'farm',
			static::OIL_REFINERY => 'oil_refinery',
			static::STEEL_MILL => 'steel_mill',
			static::ALUMINUM_REFINERY => 'aluminum_refinery',
			static::MUNITIONS_FACTORY => 'munitions_factory',
			static::POLICE_STATION => 'police_station',
			static::HOSPITAL => 'hospital',
			static::RECYCLING_CENTER => 'recycling_center',
			static::SUBWAY => 'subway',
			static::SUPER_MARKET => 'supermarket',
			static::BANK => 'bank',
			static::SHOPPING_MALL => 'shopping_mall',
			static::STADIUM => 'stadium',
			static::BARRACKS => 'barracks',
			static::FACTORY => 'factory',
			static::HANGAR => 'hangar',
			static::DRYDOCK => 'drydock',
			default => ''
		};
	}
}
