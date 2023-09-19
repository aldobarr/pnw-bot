<?php

namespace App\Enums;

enum Continent: string {
	case EUROPE = 'eu';
	case ASIA = 'as';
	case NORTH_AMERICA = 'na';
	case SOUTH_AMERICA = 'sa';
	CASE AUSTRALIA = 'au';
	CASE ANTARTICA = 'an';
	case AFRICA = 'af';

	public function getAvailableRaws(): array {
		return match($this) {
			static::EUROPE => [Resource::IRON, Resource::COAL, Resource::LEAD, Resource::FOOD],
			static::ASIA => [Resource::IRON, Resource::URANIUM, Resource::OIL, Resource::FOOD],
			static::NORTH_AMERICA => [Resource::IRON, Resource::URANIUM, Resource::COAL, Resource::FOOD],
			static::SOUTH_AMERICA => [Resource::OIL, Resource::BAUXITE, Resource::LEAD, Resource::FOOD],
			static::AUSTRALIA => [Resource::COAL, Resource::BAUXITE, Resource::LEAD, Resource::FOOD],
			static::ANTARTICA => [Resource::OIL, Resource::URANIUM, Resource::COAL, Resource::FOOD],
			static::AFRICA => [Resource::OIL, Resource::URANIUM, Resource::BAUXITE, Resource::FOOD],
			default => []
		};
	}
}
