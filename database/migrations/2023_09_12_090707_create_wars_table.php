<?php

use App\Enums\WarType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::create('wars', function(Blueprint $table) {
			$table->bigInteger('id')->unsigned()->primary();
			$table->boolean('active')->index();
			$table->boolean('responded')->default(false)->index();
			$table->enum('type', array_map(fn($type) => $type->value, WarType::cases()))->index();
			$table->bigInteger('attacker_id')->unsigned();
			$table->bigInteger('attacker_alliance_id')->unsigned();
			$table->tinyInteger('attacker_resistance');
			$table->tinyInteger('attacker_action_points');
			$table->bigInteger('defender_id')->unsigned();
			$table->bigInteger('defender_alliance_id')->unsigned()->index();
			$table->tinyInteger('defender_resistance');
			$table->tinyInteger('defender_action_points');
			$table->bigInteger('ground_control')->unsigned();
			$table->bigInteger('air_superiority')->unsigned();
			$table->bigInteger('naval_blockade')->unsigned();
			$table->dateTime('declared_at');

			$table->index(['active', 'declared_at']);
			$table->index(['active', 'responded', 'defender_id']);
			$table->index(['active', 'responded', 'attacker_id', 'defender_id']);
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::dropIfExists('wars');
	}
};
