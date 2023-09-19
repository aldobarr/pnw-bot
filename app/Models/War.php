<?php

namespace App\Models;

use App\Enums\WarType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class War extends Model {
	use HasFactory;

	public const WAR_DURATION_HOURS = 120;
	public const PURGE_AFTER_HOURS = 120;
	public const MAX_OFFENSIVE_SLOTS = 5;
	public const MAX_DEFENSIVE_SLOTS = 3;

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
		'active' => 'boolean',
		'responded' => 'boolean',
		'type' => WarType::class,
		'declared_at' => 'datetime',
	];

	public function attacks(): HasMany {
		return $this->hasMany(WarAttack::class);
	}

	public static function purgeOldWars(): void {
		static::where('declared_at', '<=', now()->subHours(static::PURGE_AFTER_HOURS))->delete();
	}
}
