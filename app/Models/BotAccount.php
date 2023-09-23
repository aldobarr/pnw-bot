<?php

namespace App\Models;

use App\Mail\Contracts\Mail;
use App\Services\Browser\AccountSimulationService;
use App\Services\Browser\LoginService;
use App\Services\PoliticsAndWarAPIService;
use App\Services\VPNService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class BotAccount extends Model {
	use HasFactory;

	public const MAIN_ACCOUNT_ID = 25;
	public const NATION_DATA_COLUMNS = ['api_key' => true, 'nation_id' => true];

	/**
	 * The attributes that should be cast.
	 *
	 * @var array<string, string>
	 */
	protected $casts = [
		'verified' => 'boolean',
		'nation_created' => 'boolean',
		'tutorial_completed' => 'boolean',
		'built_first_project' => 'boolean',
		'changed_animal' => 'boolean',
		'completed_survey' => 'boolean',
		'player_controlled' => 'boolean',
		'banned' => 'boolean',
		'last_login_at' => 'datetime',
		'next_login_at' => 'datetime',
	];

	public function canLogin(): bool {
		if (!$this->nation_created || !$this->tutorial_completed || !$this->built_first_project) {
			return false;
		}

		if (empty($this->next_login_at)) {
			return true;
		}

		return Carbon::now()->greaterThanOrEqualTo($this->next_login_at);
	}

	public function login(): void {
		$vpn_service = app(VPNService::class);
		if ($vpn_service->isConnected()) {
			return;
		}

		$vpn_service->connect($this->vpn);
		if (!$vpn_service->isConnected($this->vpn)) {
			return;
		}

		try {
			$login_service = app(LoginService::class);
			$login_service->clearCookies();
			if ($login_service->login($this->email->login, $this->password)) {
				$account_simulation_service = app(AccountSimulationService::class);
				$account_simulation_service->setAccount($this);
				$account_simulation_service->setCookies($login_service->getCookies());

				if ($this->isOfficer()) {
					$account_simulation_service->approveMembers();
				}

				if (!$this->player_controlled) {
					$account_simulation_service->simulateDailyLogin();
				} else {
					$account_simulation_service->simulatePlayerControlledLogin();
				}

				$login_service->logout();
				$this->setNextLogin();
			}
		} finally {
			$vpn_service->disconnect();
		}
	}

	public function ensureVPNConnection(): void {
		$vpn_service = app(VPNService::class);
		if (!$vpn_service->isConnected($this->vpn)) {
			throw new \RuntimeException('Not connected to VPN');
		}
	}

	public function apiKey(): Attribute {
		return Attribute::make(
			get: fn($value) => $this->setNationDataAndRefreshIfEmpty('api_key', $value)
		);
	}

	public function nationId(): Attribute {
		return Attribute::make(
			get: fn($value) => $this->setNationDataAndRefreshIfEmpty('nation_id', $value)
		);
	}

	public function setNationDataAndRefreshIfEmpty(string $return_column, $value = null): ?string {
		if (!array_key_exists($return_column, static::NATION_DATA_COLUMNS)) {
			throw new \Exception('Invalid nation data column');
		}

		if (!empty($value)) {
			return $value;
		}

		$login_service = app(LoginService::class);
		if (!$login_service->createAPIKey($this)) {
			return null;
		}

		$this->refresh();
		return $this->getOriginal($return_column) ?? null;
	}

	public function getNationData(): ?object {
		$this->ensureVPNConnection();
		if (empty($this->api_key) || empty($this->nation_id)) {
			return null;
		}

		return (new PoliticsAndWarAPIService($this->api_key))->getNation($this->nation_id);
	}

	public function getCityData(): ?array {
		$this->ensureVPNConnection();
		if (empty($this->api_key) || empty($this->nation_id)) {
			return null;
		}

		return (new PoliticsAndWarAPIService($this->api_key))->getCities($this->nation_id);
	}

	public function getResources(): ?array {
		$this->ensureVPNConnection();
		if (empty($this->api_key) || empty($this->nation_id)) {
			return null;
		}

		return (new PoliticsAndWarAPIService($this->api_key))->getResources();
	}

	public function getNationSimData(): ?object {
		$this->ensureVPNConnection();
		if (empty($this->api_key) || empty($this->nation_id)) {
			return null;
		}

		return (new PoliticsAndWarAPIService($this->api_key))->getNationSelf();
	}

	public function isOfficer(): bool {
		if (empty($this->nation_id) || empty($this->nation)) {
			return false;
		}

		return $this->nation->alliance_id === AccountSimulationService::ALLIANCE_ID && strcasecmp($this->nation->data->alliance_position, 'LEADER') === 0;
	}

	protected function password(): Attribute {
		return Attribute::make(
			get: fn(string $value) => decrypt($value),
			set: fn(string $value) => encrypt($value)
		);
	}

	public function getVPNServer(VPNService $vpn_service): string {
		if (!empty($this->vpn)) {
			return $this->vpn;
		}

		$this->vpn = $vpn_service->findServer(BotAccount::getUsedMap());
		return $this->vpn;
	}

	public function setNextLogin(): void {
		$this->last_login_at = Carbon::now();
		$next_hour = $this->getNextLoginHours();
		$this->next_login_at = Carbon::now()->addHours($next_hour)->addMinutes(random_int($next_hour === 0 ? 30 : 0, 60));
		$this->save();
	}

	private function getNextLoginHours(): int {
		$now = Carbon::now('America/New_York');
		$min = Carbon::today('America/New_York')->addHours(5);
		$max = Carbon::today('America/New_York')->addHours(20);
		if ($now->betweenIncluded($min, $max)) {
			return random_int(0, 3);
		}

		return random_int(10, 14);
	}

	public function canBuyMil(): bool {
		return empty($this->last_login_at) || LoginService::getLastResetTime()->greaterThanOrEqualTo($this->last_login_at);
	}

	public function nation(): BelongsTo {
		return $this->belongsTo(Nation::class);
	}

	public function capital(): BelongsTo {
		return $this->belongsTo(WorldCity::class, 'capital_id');
	}

	public function email(): BelongsTo {
		return $this->belongsTo(Email::class);
	}

	public function getMailHandler(): Mail {
		return $this->email->getMailHandler();
	}

	public function events(): HasMany {
		return $this->hasMany(Event::class, 'account_id');
	}

	public function sameNetwork(): Attribute {
		return Attribute::make(fn() => $this->sameNetworkFirst->merge($this->sameNetworkSecond));
	}

	public function sameNetworkFirst(): BelongsToMany {
		return $this->belongsToMany(static::class, 'same_network', 'first_account_id', 'second_account_id');
	}

	public function sameNetworkSecond(): BelongsToMany {
		return $this->belongsToMany(static::class, 'same_network', 'second_account_id', 'first_account_id');
	}

	public static function getUsedMap(): array {
		return BotAccount::whereNotNull('vpn')->pluck('vpn', 'vpn')->toArray();
	}

	public static function getMainAccount(): BotAccount {
		return BotAccount::where('id', static::MAIN_ACCOUNT_ID)->first();
	}
}
