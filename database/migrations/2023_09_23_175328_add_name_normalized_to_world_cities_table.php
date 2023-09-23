<?php

use App\Models\WorldCity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		setlocale(LC_ALL, 'en_US.utf8');
		Schema::table('world_cities', function(Blueprint $table) {
			$table->string('name_normalized')->after('name');
		});

		$upserts = [];
		foreach (WorldCity::lazy() as $city) {
			$upserts[] = [
				'id' => $city->id,
				'name' => $city->name,
				'name_normalized' => $this->normalize($city->name),
				'country' => $city->country,
				'country_iso' => $city->country_iso,
				'lat' => $city->lat,
				'lng' => $city->lng,
			];

			if (count($upserts) >= 2000) {
				WorldCity::upsert($upserts, 'id');
				unset($upserts);
				$upserts = [];
			}
		}

		if (!empty($upserts)) {
			WorldCity::upsert($upserts, 'id');
			unset($upserts);
			$upserts = [];
		}
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::table('world_cities', function(Blueprint $table) {
			$table->dropColumn('name_normalized');
		});
	}

	private function normalize(string $name): string {
		return preg_replace('/[^A-Za-z0-9\-&\s]/', '', @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: '');
	}
};
