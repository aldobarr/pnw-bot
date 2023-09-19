<?php

namespace App\Enums;

enum Resource: string {
	use FromNormalized;

	case MONEY = 'money';
	case FOOD = 'food';
	case STEEL = 'steel';
	case ALUMINUM = 'aluminum';
	case GASOLINE = 'gasoline';
	case MUNITIONS = 'munitions';
	case URANIUM = 'uranium';
	case COAL = 'coal';
	case OIL = 'oil';
	case LEAD = 'lead';
	case IRON = 'iron';
	case BAUXITE = 'bauxite';
	case CREDITS = 'credits';

	public static function all(string|array|Resource $except = []): array {
		if (!is_array($except)) {
			$except = [$except instanceof Resource ? $except->value : $except => true];
		} else {
			$arr = [];
			foreach ($except as $except) {
				$arr[$except instanceof Resource ? $except->value : $except] = true;
			}

			$except = $arr;
		}

		$values = [];
		foreach (static::cases() as $resource) {
			if (array_key_exists($resource->value, $except)) {
				continue;
			}

			$values[] = $resource->value;
		}

		return $values;
	}

	public static function casesExcept(Resource $except): array {
		return array_filter(static::cases(), fn($resource) => $resource !== $except);
	}

	public function keepAmount(): int {
		return match($this) {
			static::MONEY => 1000000,
			static::FOOD => 20000,
			static::URANIUM => 200,
			static::STEEL => 500,
			static::ALUMINUM => 500,
			static::GASOLINE => 1000,
			static::MUNITIONS => 1000,
			default => 0
		};
	}

	public function improvement(): Improvement {
		return match($this) {
			static::OIL => Improvement::OIL_WELL,
			static::COAL => Improvement::COAL_MINE,
			static::IRON => Improvement::IRON_MINE,
			static::BAUXITE => Improvement::BAUXITE_MINE,
			static::LEAD => Improvement::LEAD_MINE,
			static::URANIUM => Improvement::URANIUM_MINE,
			static::FOOD => Improvement::FARM,
			static::STEEL => Improvement::STEEL_MILL,
			static::ALUMINUM => Improvement::ALUMINUM_REFINERY,
			static::GASOLINE => Improvement::OIL_REFINERY,
			static::MUNITIONS => Improvement::MUNITIONS_FACTORY,
			default => Improvement::NONE
		};
	}
}
