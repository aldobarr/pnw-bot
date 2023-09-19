<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::create('war_attacks', function(Blueprint $table) {
			$table->bigInteger('id')->unsigned()->primary();
			$table->bigInteger('war_id')->unsigned()->index();
			$table->string('type');
			$table->bigInteger('attacker_id')->unsigned();
			$table->bigInteger('defender_id')->unsigned();
			$table->integer('food')->unsigned();
			$table->integer('uranium')->unsigned();
			$table->integer('oil')->unsigned();
			$table->integer('coal')->unsigned();
			$table->integer('iron')->unsigned();
			$table->integer('lead')->unsigned();
			$table->integer('aluminum')->unsigned();
			$table->integer('steel')->unsigned();
			$table->integer('bauxite')->unsigned();
			$table->integer('gasoline')->unsigned();
			$table->integer('munitions')->unsigned();
			$table->bigInteger('money_stolen')->unsigned();
			$table->bigInteger('money_looted')->unsigned();
			$table->dateTime('attacked_at')->index();

			$table->index(['war_id', 'money_looted']);
			$table->index(['attacker_id', 'defender_id']);
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::dropIfExists('war_attacks');
	}
};
