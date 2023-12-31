<?php

namespace App\Services;

use Ballen\Distical\Calculator;
use Ballen\Distical\Entities\LatLong;

class MapService {
	public const BOUNDING_BOX_RADIUS_RANGE = [7500, 7850]; // decimal miles represented as integer * 1000
	public const COORDINATE_ROUND_PRECISION = 3;
	public const COORDINATE_CENTER_ROUND_PRECISION = 2;

	private float $latitude;
	private float $longitude;
	private CityBounds $bounds;

	public function __construct(float $latitude, float $longitude) {
		$this->latitude = $latitude;
		$this->longitude = $longitude;
		$this->calculateBounds(static::getRandomRadius(), static::COORDINATE_ROUND_PRECISION, true);
	}

	public function calculateBounds(float $distance_in_miles, ?int $precision = null, bool $add_variance = false): void {
		$radius = 3963.1; // of earth in miles

		// bearings - FIX
		$due_north = deg2rad(0);
		$due_south = deg2rad(180);
		$due_east = deg2rad(90);
		$due_west = deg2rad(270);

		// convert latitude and longitude into radians
		$lat_r = deg2rad($this->latitude);
		$lon_r = deg2rad($this->longitude);

		// find the northmost, southmost, eastmost and westmost corners $distance_in_miles away
		// original formula from
		// http://www.movable-type.co.uk/scripts/latlong.html

		$northmost  = asin(sin($lat_r) * cos($distance_in_miles / $radius) + cos($lat_r) * sin ($distance_in_miles / $radius) * cos($due_north));
		$southmost  = asin(sin($lat_r) * cos($distance_in_miles / $radius) + cos($lat_r) * sin ($distance_in_miles / $radius) * cos($due_south));

		$eastmost = $lon_r + atan2(sin($due_east) * sin($distance_in_miles / $radius) * cos($lat_r), cos($distance_in_miles / $radius) - sin($lat_r) * sin($lat_r));
		$westmost = $lon_r + atan2(sin($due_west) * sin($distance_in_miles / $radius) * cos($lat_r), cos($distance_in_miles / $radius) - sin($lat_r) * sin($lat_r));


		$northmost = rad2deg($northmost);
		$southmost = rad2deg($southmost);
		$eastmost = rad2deg($eastmost);
		$westmost = rad2deg($westmost);

		if ($precision !== null && $precision >= 0) {
			$northmost = round($northmost, $precision);
			$southmost = round($southmost, $precision);
			$eastmost = round($eastmost, $precision);
			$westmost = round($westmost, $precision);
		}

		$lat1 = $northmost;
		$lat2 = $southmost;
		$lng1 = $eastmost;
		$lng2 = $westmost;

		// sort the lat and long so that we can use them for a between query
		if ($northmost > $southmost) {
			$lat1 = $southmost;
			$lat2 = $northmost;
		}

		if ($eastmost > $westmost) {
			$lng1 = $westmost;
			$lng2 = $eastmost;
		}

		$this->bounds = new CityBounds(
			$lat1, $lng2,
			$lat1, $lng1,
			$lat2, $lng1,
			$lat2, $lng2,
			$add_variance
		);
	}

	public function getBounds(): CityBounds {
		return $this->bounds;
	}

	public static function getRandomRadius(): float {
		[$min, $max] = static::BOUNDING_BOX_RADIUS_RANGE;
		$value = random_int($min, $max);
		return round($value / 1000, 3);
	}

	public static function addVariance(float $lat1, float $lat2, float $lng1, float $lng2): array {

		return [$lat1, $lat2, $lng1, $lng2];
	}
}

class CityBounds {
	private LatLong $northwest;
	private LatLong $northeast;
	private LatLong $southeast;
	private LatLong $southwest;

	public function __construct(
		float $nw_lat, float $nw_lng,
		float $ne_lat, float $ne_lng,
		float $se_lat, float $se_lng,
		float $sw_lat, float $sw_lng,
		bool $add_variance
	) {
		$this->northwest = new LatLong($nw_lat, $nw_lng);
		$this->northeast = new LatLong($add_variance ? static::addPointVariance($ne_lat) : $ne_lat, $add_variance ? static::addPointVariance($ne_lng) : $ne_lng);
		$this->southeast = new LatLong($add_variance ? static::addPointVariance($se_lat) : $se_lat, $add_variance ? static::addPointVariance($se_lng) : $se_lng);
		$this->southwest = new LatLong($add_variance ? static::addPointVariance($sw_lat) : $sw_lat, $add_variance ? static::addPointVariance($sw_lng) : $sw_lng);
	}

	public function toArray(): array {
		return [
			['lat' => $this->northwest->getLatitude(), 'lng' => $this->northwest->getLongitude()],
			['lat' => $this->northeast->getLatitude(), 'lng' => $this->northeast->getLongitude()],
			['lat' => $this->southeast->getLatitude(), 'lng' => $this->southeast->getLongitude()],
			['lat' => $this->southwest->getLatitude(), 'lng' => $this->southwest->getLongitude()],
		];
	}

	public function calculateArea(): float {
		$center_distance = abs((new Calculator($this->northwest, $this->southeast))->get()->asMiles());
		$height_triangle1 = abs((new Calculator($this->northwest, $this->southwest))->get()->asMiles());
		$length_triangle1 = abs((new Calculator($this->southwest, $this->southeast))->get()->asMiles());
		$sp_triangle1 = ($center_distance + $height_triangle1 + $length_triangle1) / 2;

		$height_triangle2 = abs((new Calculator($this->northwest, $this->northeast))->get()->asMiles());
		$length_triangle2 = abs((new Calculator($this->northeast, $this->southeast))->get()->asMiles());
		$sp_triangle2 = ($center_distance + $height_triangle2 + $length_triangle2) / 2;

		$area_triangle1 = sqrt($sp_triangle1 * ($sp_triangle1 - $center_distance) * ($sp_triangle1 - $height_triangle1) * ($sp_triangle1 - $length_triangle1));
		$area_triangle2 = sqrt($sp_triangle2 * ($sp_triangle2 - $center_distance) * ($sp_triangle2 - $height_triangle2) * ($sp_triangle2 - $length_triangle2));
		return $area_triangle1 + $area_triangle2;
	}

	public function calculateCenterPoint(): LatLong {
		$bounds = $this->toArray();
		$max_lng = PHP_FLOAT_MIN;
		$min_lng = PHP_FLOAT_MAX;
		$shift = $sum_lat = $sum_lng = 0;
		foreach ($bounds as $coordinates) {
			$lat = $coordinates['lat'];
			$lng = $coordinates['lng'];
			$sum_lat += $lat;
			$sum_lng += $lng;

			if ($max_lng < $lng) {
				$max_lng = $lng;
			}

			if ($min_lng > $lng) {
				$min_lng = $lng;
			}

			if ($lng < 0) {
				$shift += 360;
			}
		}

		if ($max_lng - $min_lng > 180) {
			$sum_lng += $shift;
		}

		$lng = $sum_lng / count($bounds);
		if ($lng > 180) {
			$lng -= 360;
		}

		if ($lng < -180) {
			$lng += 360;
		}

		return new LatLong(
			round($sum_lat / count($bounds), MapService::COORDINATE_CENTER_ROUND_PRECISION),
			round($lng, MapService::COORDINATE_CENTER_ROUND_PRECISION)
		);
	}

	public static function addPointVariance(float $point): float {
		return $point + ((random_int(1, 100) <= 50 ? random_int(-5, 12) : random_int(-12, 7)) / 100);
	}
}
