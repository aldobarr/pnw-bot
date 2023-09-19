<?php

namespace App\Enums;

enum Project: string {
	use FromNormalized;

	CASE ACTIVITY_CENTER = 'activity_center';
	case ADVANCED_PIRATE_ECONOMY = 'advanced_pirate_economy';
	case PIRATE_ECONOMY = 'pirate_economy';
	case PROPAGANDA_BUREAU = 'propaganda_bureau';

	public function getPurchaseName(): string {
		return match($this) {
			static::ACTIVITY_CENTER => 'buy_rpc_np',
			static::ADVANCED_PIRATE_ECONOMY => 'buy_adv_pirate_economy',
			static::PIRATE_ECONOMY => 'buy_pirate_economy',
			static::PROPAGANDA_BUREAU => 'buy_propb',
			default => ''
		};
	}

	public function getPurchaseCost(): array {
		return match($this) {
			static::ACTIVITY_CENTER => [
				Resource::MONEY->value => 500000,
				Resource::FOOD->value => 1000
			],
			static::ADVANCED_PIRATE_ECONOMY => [
				Resource::MONEY->value => 50000000,
				Resource::ALUMINUM->value => 20000,
				Resource::MUNITIONS->value => 40000,
				Resource::GASOLINE->value => 20000
			],
			static::PIRATE_ECONOMY => [
				Resource::MONEY->value => 25000000,
				Resource::ALUMINUM->value => 10000,
				Resource::MUNITIONS->value => 10000,
				Resource::GASOLINE->value => 10000,
				Resource::STEEL->value => 10000
			],
			static::PROPAGANDA_BUREAU => [
				Resource::MONEY->value => 15000000,
				Resource::ALUMINUM->value => 1500
			],
			default => []
		};
	}
}