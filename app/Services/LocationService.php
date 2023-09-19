<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LocationService {
	public static function getLocation(): ?object {
		$location_request = Http::retry(5, 1250)->acceptJson()->get('https://ipwho.is/');
		if (!$location_request->successful()) {
			return null;
		}

		$location = $location_request->object();
		if (!$location->success) {
			return null;
		}

		return $location;
	}
}
