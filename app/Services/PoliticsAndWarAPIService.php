<?php

namespace App\Services;

use App\Enums\Resource;
use App\Models\MarketSnapshot;
use App\Models\Nation;
use App\Models\War;
use App\Models\WarAttack;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\RetryMiddleware;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Softonic\GraphQL\Client as GraphQLClient;
use Softonic\GraphQL\DataObjectBuilder;
use Softonic\GraphQL\ResponseBuilder;
use Symfony\Component\Console\Helper\ProgressBar;

class PoliticsAndWarAPIService {
	private string $apiKey;
	private PendingRequest $client;
	private GraphQLClient $graphQL;
	private const SCHEMA_CACHE = ['api.schema.nation', 'api.schema.city'];
	private const VALID_FIELD_KINDS = ['scalar' => true, 'enum' => true];
	private const TIMEOUT = 30;
	private const CONNECT_TIMEOUT = 10;
	public const MAX_RETRIES = 3;

	public function __construct(string $api_key) {
		$this->validateSchemaCache();
		$this->apiKey = $api_key;
		$this->client = Http::withOptions([
			'base_uri' => 'https://politicsandwar.com/api/',
			'timeout' => static::TIMEOUT,
			'connect_timeout' => static::CONNECT_TIMEOUT,
		])->retry(static::MAX_RETRIES, 1250)->acceptJson();

		$this->graphQL = $this->buildGraphQLClient($api_key);
	}

	public function getNation(int $nation_id): ?object {
		$nation_schema = Cache::remember('api.schema.nation', now()->addDays(30), fn() => $this->getSchema('Nation'));
		$fields = implode(', ', $this->getNonObjectFields($nation_schema));
		$query = <<<QUERY
		query GetNation(\$nation_id: [Int]) {
			nations(id: \$nation_id) {
				data {
					$fields
				}
			}
		}
		QUERY;

		$nation_data = $this->graphQL->query($query, ['nation_id' => $nation_id]);
		if ($nation_data->hasErrors()) {
			return null;
		}

		$data = json_decode(json_encode($nation_data->getData()));
		return $data->nations->data[0] ?? null;
	}

	public function getMyWars(bool $skip_import = false): array {
		$war_fields = implode(', ', $this->getWarFields());
		$query = <<<QUERY
		query {
			me {
				nation {
					wars(active: true, orderBy: {column: ID, order: DESC}) {
						$war_fields
					}
				}
			}
		}
		QUERY;

		$nation_data = $this->graphQL->query($query);
		if ($nation_data->hasErrors()) {
			return null;
		}

		$data = json_decode(json_encode($nation_data->getData()));
		if (!$skip_import) {
			$war_inserts = [];
			foreach ($data->me->nation->wars ?? [] as $war) {
				$war_inserts[$war->id] = [
					'id' => $war->id,
					'active' => $war->turns_left > 0 && (!$war->att_peace || !$war->def_peace) && intval($war->winner_id) === 0,
					'type' => $war->war_type,
					'attacker_id' => $war->att_id,
					'attacker_alliance_id' => $war->att_alliance_id,
					'attacker_resistance' => $war->att_resistance,
					'attacker_action_points' => $war->att_points,
					'defender_id' => $war->def_id,
					'defender_alliance_id' => $war->def_alliance_id,
					'defender_resistance' => $war->def_resistance,
					'defender_action_points' => $war->def_points,
					'ground_control' => $war->ground_control,
					'air_superiority' => $war->air_superiority,
					'naval_blockade' => $war->naval_blockade,
					'declared_at' => Carbon::parse($war->date)
				];
			}

			War::upsert($war_inserts, 'id');
		}

		return $data->me->nation->wars ?? [];
	}

	public function getSchema(string $type): ?array {
		$query = <<<'QUERY'
		query GetSchema($type: String!) {
			__type(name: $type) {
				fields {
					name,
					type {
						name,
						kind
					}
				}
			}
		}
		QUERY;

		$response = $this->graphQL->query($query, ['type' => $type]);
		if ($response->hasErrors()) {
			return null;
		}

		$data = $response->getData();
		if (empty($data)) {
			return null;
		}

		return array_map(fn($field) => json_decode(json_encode($field)), $data['__type']['fields']);
	}

	public function getCities(int $nation_id): ?array {
		$city_schema = Cache::remember('api.schema.city', now()->addDays(30), fn() => $this->getSchema('City'));
		$fields = implode(', ', $this->getNonObjectFields($city_schema));
		$query = <<<QUERY
		query GetCities(\$nation_id: [Int]) {
			cities(nation_id: \$nation_id) {
				data {
					$fields
				}
			}
		}
		QUERY;

		$cities_data = $this->graphQL->query($query, ['nation_id' => $nation_id]);
		if ($cities_data->hasErrors()) {
			return null;
		}

		$data = json_decode(json_encode($cities_data->getData()));
		return $data->cities->data ?? null;
	}

	public function getMarketData(string $resource): ?object {
		$response = $this->client->get('/tradeprice/?resource=' . $resource . '&key=' . $this->apiKey);
		if (!$response->successful()) {
			return null;
		}

		return $response->object();
	}

	public function getNationSelf(): ?object {
		$nation_schema = Cache::remember('api.schema.nation', now()->addDays(30), fn() => $this->getSchema('Nation'));
		$nation_fields = implode(', ', $this->getNonObjectFields($nation_schema));
		$city_schema = Cache::remember('api.schema.city', now()->addDays(30), fn() => $this->getSchema('City'));
		$city_fields = implode(', ', $this->getNonObjectFields($city_schema));

		$query = <<<QUERY
		query {
			me {
				nation {
					$nation_fields,
					cities {
						$city_fields
					}
				}
			}
		}
		QUERY;

		$response = $this->graphQL->query($query);
		if ($response->hasErrors()) {
			return null;
		}

		$data = json_decode(json_encode($response->getData()));
		return $data->me->nation ?? null;
	}

	public function getResources(): ?array {
		$resources = implode(', ', Resource::all());
		$query = <<<QUERY
		query {
			me {
				nation {
					$resources
				}
			}
		}
		QUERY;

		$response = $this->graphQL->query($query);
		if ($response->hasErrors()) {
			return null;
		}

		$data = $response->getData();
		return $data['me']['nation'] ?? null;
	}

	public function importNations(ProgressBar $bar): void {
		$nation_schema = Cache::remember('api.schema.nation', now()->addDays(30), fn() => $this->getSchema('Nation'));
		$fields = $this->getNonObjectFields($nation_schema);
		$fields_str = implode(', ', $fields);

		$query = <<<QUERY
		query GetNations(\$page: Int) {
			nations(first: 500, page: \$page) {
				data {
					$fields_str
				},
				paginatorInfo {
					count,
					total,
					currentPage,
					hasMorePages,
					lastPage
				}
			}
		}
		QUERY;

		$start = true;
		$page = 1;
		$data = [];

		do {
			$response = $this->graphQL->query($query, ['page' => $page++]);
			if ($response->hasErrors()) {
				return;
			}

			$data = $response->getData();

			if ($start) {
				$bar->start($data['nations']['paginatorInfo']['lastPage']);
				$start = false;
			}

			$nation_inserts = [];
			foreach ($data['nations']['data'] as $nation) {
				$nation_inserts[] = ['id' => $nation['id'], 'data' => json_encode($nation)];
			}

			Nation::upsert($nation_inserts, 'id');
			unset($nation_inserts);
			$bar->advance();
		} while ($data['nations']['paginatorInfo']['hasMorePages'] ?? false);

		$bar->finish();
	}

	public function importWars(ProgressBar $bar, int $days_ago = 1): void {
		$war_fields = implode(', ', $this->getWarFields());
		$query = <<<QUERY
		query GetWars(\$page: Int, \$days_ago: Int) {
			wars(first: 1000, page: \$page, active: false, days_ago: \$days_ago) {
				data {
					$war_fields,
					attacks {
						id,
						type,
						att_id,
						def_id,
						money_stolen,
						money_looted,
						oil_looted,
						coal_looted,
						iron_looted,
						lead_looted,
						food_looted,
						steel_looted,
						uranium_looted,
						bauxite_looted,
						gasoline_looted,
						aluminum_looted,
						munitions_looted,
						date
					}
				},
				paginatorInfo {
					count,
					total,
					currentPage,
					hasMorePages,
					lastPage
				}
			}
		}
		QUERY;

		$start = true;
		$page = 1;
		$data = [];

		do {
			$response = $this->graphQL->query($query, ['page' => $page++, 'days_ago' => $days_ago]);
			if ($response->hasErrors()) {
				return;
			}

			$data = $response->getData();

			if ($start) {
				$bar->start($data['wars']['paginatorInfo']['lastPage']);
				$start = false;
			}

			$attack_inserts = $war_inserts = [];
			foreach ($data['wars']['data'] as $war) {
				if (!empty($war['attacks']) && is_array($war['attacks'])) {
					foreach ($war['attacks'] as $attack) {
						$attack_inserts[$attack['id']] = [
							'id' => $attack['id'],
							'war_id' => $war['id'],
							'type' => $attack['type'],
							'attacker_id' => $attack['att_id'],
							'defender_id' => $war['def_id'],
							'oil' => intval(round($attack['oil_looted'] * 100)),
							'coal' => intval(round($attack['coal_looted'] * 100)),
							'iron' => intval(round($attack['iron_looted'] * 100)),
							'lead' => intval(round($attack['lead_looted'] * 100)),
							'food' => intval(round($attack['food_looted'] * 100)),
							'steel' => intval(round($attack['steel_looted'] * 100)),
							'uranium' => intval(round($attack['uranium_looted'] * 100)),
							'bauxite' => intval(round($attack['bauxite_looted'] * 100)),
							'gasoline' => intval(round($attack['gasoline_looted'] * 100)),
							'aluminum' => intval(round($attack['aluminum_looted'] * 100)),
							'munitions' => intval(round($attack['munitions_looted'] * 100)),
							'money_stolen' => intval(round($attack['money_stolen'] * 100)),
							'money_looted' => intval(round($attack['money_looted'] * 100)),
							'attacked_at' => Carbon::parse($attack['date'])
						];
					}
				}

				$war_inserts[$war['id']] = [
					'id' => $war['id'],
					'active' => $war['turns_left'] > 0 && (!$war['att_peace'] || !$war['def_peace']) && intval($war['winner_id']) === 0,
					'type' => $war['war_type'],
					'attacker_id' => $war['att_id'],
					'attacker_alliance_id' => $war['att_alliance_id'],
					'attacker_resistance' => $war['att_resistance'],
					'attacker_action_points' => $war['att_points'],
					'defender_id' => $war['def_id'],
					'defender_alliance_id' => $war['def_alliance_id'],
					'defender_resistance' => $war['def_resistance'],
					'defender_action_points' => $war['def_points'],
					'ground_control' => $war['ground_control'],
					'air_superiority' => $war['air_superiority'],
					'naval_blockade' => $war['naval_blockade'],
					'declared_at' => Carbon::parse($war['date'])
				];
			}

			War::upsert($war_inserts, 'id');
			$attack_inserts = array_chunk($attack_inserts, 1000);
			foreach ($attack_inserts as $insert) {
				WarAttack::upsert($insert, 'id');
			}

			unset($war_inserts, $attack_inserts);
			$bar->advance();
		} while ($data['wars']['paginatorInfo']['hasMorePages'] ?? false);

		$bar->finish();
	}

	public static function calculateResourceAveragePrices(?Carbon $date_time = null): ?array {
		if (empty($date_time)) {
			$date_time = now();
		}

		$data = MarketSnapshot::all();
		$organized_data = [];

		foreach ($data as $snapshot) {
			$time = $snapshot->imported_at->format('dm');
			if (!array_key_exists($time, $organized_data)) {
				$organized_data[$time] = [
					Resource::FOOD->value => ['high' => [], 'low' => [], 'time' => $snapshot->imported_at],
					Resource::STEEL->value => ['high' => [], 'low' => [], 'time' => $snapshot->imported_at],
					Resource::ALUMINUM->value => ['high' => [], 'low' => [], 'time' => $snapshot->imported_at],
					Resource::GASOLINE->value => ['high' => [], 'low' => [], 'time' => $snapshot->imported_at],
					Resource::MUNITIONS->value => ['high' => [], 'low' => [], 'time' => $snapshot->imported_at],
					Resource::URANIUM->value => ['high' => [], 'low' => [], 'time' => $snapshot->imported_at],
					Resource::COAL->value => ['high' => [], 'low' => [], 'time' => $snapshot->imported_at],
					Resource::OIL->value => ['high' => [], 'low' => [], 'time' => $snapshot->imported_at],
					Resource::LEAD->value => ['high' => [], 'low' => [], 'time' => $snapshot->imported_at],
					Resource::IRON->value => ['high' => [], 'low' => [], 'time' => $snapshot->imported_at],
					Resource::BAUXITE->value => ['high' => [], 'low' => [], 'time' => $snapshot->imported_at],
					Resource::CREDITS->value => ['high' => [], 'low' => [], 'time' => $snapshot->imported_at],
				];
			}

			$organized_data[$time][$snapshot->resource]['high'][] = $snapshot->high_buy;
			$organized_data[$time][$snapshot->resource]['low'][] = $snapshot->low_buy;
		}

		if (empty($organized_data[$date_time->format('dm')]) && $date_time->isCurrentDay()) {
			return static::calculateResourceAveragePrices($date_time->subDay());
		}

		$prices = [
			Resource::FOOD->value => 0,
			Resource::STEEL->value => 0,
			Resource::ALUMINUM->value => 0,
			Resource::GASOLINE->value => 0,
			Resource::MUNITIONS->value => 0,
			Resource::URANIUM->value => 0,
			Resource::COAL->value => 0,
			Resource::OIL->value => 0,
			Resource::LEAD->value => 0,
			Resource::IRON->value => 0,
			Resource::BAUXITE->value => 0,
			Resource::CREDITS->value => 0
		];

		$resources = $organized_data[$date_time->format('dm')] ?? [];
		foreach ($resources as $resource => $resource_data) {
			$resource_data['high'] = static::remove_outliers($resource_data['high']);
			$high_avg = ((double)(((double)array_sum($resource_data['high'])) / ((double)count($resource_data['high']))));
			$resource_data['low'] = static::remove_outliers($resource_data['low']);
			$low_avg = ((double)(((double)array_sum($resource_data['low'])) / ((double)count($resource_data['low']))));
			$actual_avg = ((double)(($high_avg + $low_avg) / 2.0));
			$prices[$resource] = (double)round($actual_avg, 2);
		}

		return $prices;
	}

	public function getAllianceResources(): array {
		$resources = [];
		foreach (Resource::all(Resource::CREDITS) as $resource) {
			$resources[$resource] = 0;
		}

		$fields = implode(', ', array_keys($resources));
		$query = <<<QUERY
		{
			me {
				nation {
					alliance {
						$fields
					}
				}
			}
		}
		QUERY;

		$response = $this->graphQL->query($query);
		if (empty($response) || $response->hasErrors()) {
			return [];
		}

		return $response->getData()['me']['nation']['alliance'] ?? [];
	}

	private function getWarFields(): array {
		return [
			'id',
			'att_id',
			'def_id',
			'war_type',
			'att_alliance_id',
			'def_alliance_id',
			'att_resistance',
			'def_resistance',
			'att_points',
			'def_points',
			'ground_control',
			'air_superiority',
			'naval_blockade',
			'att_peace',
			'def_peace',
			'turns_left',
			'winner_id',
			'date'
		];
	}

	private static function remove_outliers($dataset, $magnitude = 1) {
		$fn = function($x, $mean) {
			return (($x - $mean) ** 2);
		};

		$count = count($dataset);
		$mean = array_sum($dataset) / $count; // Calculate the mean
		$deviation = sqrt(array_sum(array_map($fn, $dataset, array_fill(0, $count, $mean))) / $count) * $magnitude; // Calculate standard deviation and times by magnitude

		return array_filter($dataset, function($x) use ($mean, $deviation) { return ($x <= $mean + $deviation && $x >= $mean - $deviation); }); // Return filtered array of values that lie within $mean +- $deviation.
	}

	private function getNonObjectFields(array $schema): array {
		$fields = [];
		$skip_error_fields = ['government_type' => true];
		foreach ($schema as $field) {
			if (
				!array_key_exists(strtolower($field->type->kind), static::VALID_FIELD_KINDS) ||
				array_key_exists(strtolower($field->name), $skip_error_fields)
			) {
				continue;
			}

			$fields[] = $field->name;
		}

		return $fields;
	}

	private function validateSchemaCache(): void {
		foreach (static::SCHEMA_CACHE as $key) {
			if (Cache::has($key) && Cache::get($key) === null) {
				Cache::forget($key);
			}
		}
	}

	private function buildGraphQLClient(): GraphQLClient {
		$stack = HandlerStack::create();
		$stack->push(Middleware::retry(function(int $retries, RequestInterface $request, ?ResponseInterface $response): bool {
			return $retries < PoliticsAndWarAPIService::MAX_RETRIES && ($response === null || $response->getStatusCode() !== 429);
		}, function(int $retries, ?ResponseInterface $response) {
			if (empty($response) || !$response->hasHeader('Retry-After')) {
				return RetryMiddleware::exponentialDelay($retries);
			}

			$retry_after = $response->getHeaderLine('Retry-After');
			if (!is_numeric($retry_after)) {
				$date_time = Carbon::parse($retry_after);
				$retry_after = $date_time->getTimestamp() - time();
			}

			return intval($retry_after * 1000);
		}));

		return new GraphQLClient(
			new GuzzleClient([
				'handler' => $stack,
				'base_uri' => 'https://api.politicsandwar.com/graphql?api_key=' . $this->apiKey,
				'timeout' => static::TIMEOUT,
				'connect_timeout' => static::CONNECT_TIMEOUT,
			]),
			new ResponseBuilder(new DataObjectBuilder)
		);
	}
}
