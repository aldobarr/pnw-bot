<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::create('market_snapshots', function(Blueprint $table) {
			$table->id();
			$table->string('resource');
			$table->decimal('high_buy', 12, 2);
			$table->decimal('low_buy', 12, 2);
			$table->decimal('avg', 12, 2);
			$table->datetime('imported_at');

			$table->index('resource');
			$table->index('imported_at');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::dropIfExists('market_snapshots');
	}
};
