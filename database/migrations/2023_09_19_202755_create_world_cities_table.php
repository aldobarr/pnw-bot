<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::create('world_cities', function(Blueprint $table) {
			$table->id();
			$table->string('name');
			$table->string('country');
			$table->string('country_iso');
			$table->decimal('lat', 8, 6);
			$table->decimal('lng', 9, 6);
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::dropIfExists('world_cities');
	}
};
