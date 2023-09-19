<?php

namespace App\Models;

use App\Enums\NationColor;
use App\Enums\Resource;
use App\Services\PoliticsAndWarAPIService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Nation extends Model {
	use HasFactory;

	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;

	/**
	 * The attributes that should be cast.
	 *
	 * @var array<string, string>
	 */
	protected $casts = [
		'data' => 'object',
	];

	public function wars(): Attribute {
		return Attribute::make(fn() => $this->activeWars->merge($this->finishedWars));
	}

	public function finishedWars(): Attribute {
		return Attribute::make(fn() => $this->finishedOffensiveWars->merge($this->finishedDefensiveWars));
	}

	public function finishedOffensiveWars(): HasMany {
		return $this->hasMany(War::class, 'attacker_id')->where('active', 0);
	}

	public function finishedDefensiveWars(): HasMany {
		return $this->hasMany(War::class, 'defender_id')->where('active', 0);
	}

	public function activeWars(): Attribute {
		return Attribute::make(fn() => $this->offensiveWars->merge($this->defensiveWars));
	}

	public function offensiveWars(): HasMany {
		return $this->hasMany(War::class, 'attacker_id')->where('active', 1);
	}

	public function defensiveWars(): HasMany {
		return $this->hasMany(War::class, 'defender_id')->where('active', 1);
	}

	public function getOffensiveSlots(): int {
		$active_offensive_wars = War::where('active', true)->where('attacker_id', $this->id)->count();
		return (
			(
				($this->data->pirate_economy ? 1 : 0) +
				($this->data->advanced_pirate_economy ? 1 : 0) +
				War::MAX_OFFENSIVE_SLOTS
			) - $active_offensive_wars
		);
	}

	public function getDefensiveSlots(): int {
		return static::getDefensiveSlotsFor($this->id);
	}

	public function getTargetsInRange(bool $descending = true): Collection {
		return static::getTargetsInRangeFor($this->data->score, $descending);
	}

	public static function getTargetsInRangeFor(float $score, bool $descending = true): Collection {
		$resources = Resource::all([Resource::MONEY, Resource::CREDITS]);
		$resource_prices = PoliticsAndWarAPIService::calculateResourceAveragePrices();
		return static::with(['finishedOffensiveWars.attacks', 'finishedDefensiveWars.attacks', 'defensiveWars.attacks'])
				->where(function(Builder $query) {
					$query->doesntHave('defensiveWars')
						->orWhereHas('defensiveWars', null, '<', War::MAX_DEFENSIVE_SLOTS);
				})->forceIndex('nation_target_index')
				->whereBetween('score', [$score * 0.75, $score * 1.75])
				->where('color', '!=', NationColor::BEIGE->value)
				->where('alliance_id', 0)
				->where('vacation_mode', false)
				->where('soldiers', '<', 5000)
				->where('tanks', '<', 150)
				->get()->sortBy(function(&$nation) use ($resources, $resource_prices) {
					$nation->last_loot_value = 0;
					$wars = $nation->defensiveWars;
					foreach ($wars as $war) {
						$attack = $war->attacks->filter(function($attack) use ($nation) {
							return $attack->defender_id === $nation->id && (strcasecmp($attack->type, 'GROUND') === 0 || $attack->money_looted > 0);
						})->sortBy(function(&$attack) { return $attack->attacked_at; }, SORT_REGULAR, true)->first();

						if (!empty($attack) && $attack->money_stolen < 2000000 && $attack->money_looted < 2000000) {
							$value = 0;
							$attacks = $war->attacks;
							foreach ($attacks as $attack) {
								if ($attack->attacker_id === $nation->id) {
									continue;
								}

								$value += $attack->money_stolen + $attack->money_looted;
								foreach ($resources as $resource) {
									$value += $attack->$resource * $resource_prices[$resource];
								}
							}

							$nation->last_loot_value = $value;
							return $value;
						}
					}

					$wars = $nation->finishedWars;
					$last_war = $last_attack = null;
					foreach ($wars as $war) {
						$attacks = $war->attacks;
						foreach ($attacks as $attack) {
							if ($attack->attacker_id === $nation->id) {
								continue;
							}

							if (empty($last_attack)) {
								$last_attack = $attack;
								$last_war = $war;
								continue;
							}

							if ($last_attack->attacked_at->lessThan($attack->attacked_at)) {
								$last_attack = $attack;
								$last_war = $war;
							}
						}
					}

					if (empty($last_war)) {
						return 0;
					}

					$value = 0;
					if (!empty($last_war)) {
						$attacks = $last_war->attacks;
						foreach ($attacks as $attack) {
							if ($attack->attacker_id === $nation->id) {
								continue;
							}

							$value += $attack->money_stolen + $attack->money_looted;
							foreach ($resources as $resource) {
								$value += $attack->$resource * $resource_prices[$resource];
							}
						}
					}

					$nation->last_loot_value = $value;
					return $value;
				}, SORT_REGULAR, $descending);
	}

	public static function getOffensiveSlotsFor(int $nation_id): int {
		return static::where('id', $nation_id)->first()?->getOffensiveSlots() ?? 0;
	}

	public static function getDefensiveSlotsFor(int $nation_id): int {
		$active_defensive_wars = War::where('active', true)->where('defender_id', $nation_id)->count();
		return War::MAX_DEFENSIVE_SLOTS - $active_defensive_wars;
	}
}
