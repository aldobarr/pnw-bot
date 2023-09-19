<?php

namespace App\Enums;

enum MilitaryUnit: string {
	use FromNormalized;

	case SOLDIERS = 'soldiers';
	case TANKS = 'tanks';
	case AIRCRAFT = 'aircraft';
	case NAVY = 'navy';
	CASE SPIES = 'spies';
	CASE MISSILES = 'missiles';
	case NUKES = 'nukes';

	public function getPurchaseCost(): array {
		return match($this) {
			static::SOLDIERS => [Resource::MONEY->value => 5],
			static::TANKS => [Resource::MONEY->value => 60, Resource::STEEL->value => 0.5],
			static::AIRCRAFT => [Resource::MONEY->value => 4000, Resource::ALUMINUM->value => 5],
			static::NAVY => [Resource::MONEY->value => 50000, Resource::STEEL->value => 30],
			static::SPIES => [Resource::MONEY->value => 50000],
			static::MISSILES => [Resource::MONEY->value => 150000, Resource::ALUMINUM->value => 100, Resource::GASOLINE->value => 75, Resource::MUNITIONS->value => 75],
			static::NUKES => [Resource::MONEY->value => 1750000, Resource::ALUMINUM->value => 750, Resource::GASOLINE->value => 500, Resource::URANIUM->value => 250],
			default => []
		};
	}
}
