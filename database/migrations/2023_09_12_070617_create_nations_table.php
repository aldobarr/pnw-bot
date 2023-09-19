<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::create('nations', function(Blueprint $table) {
			$table->bigInteger('id')->unsigned()->primary();
			$table->json('data');

			$table->bigInteger('alliance_id')->unsigned()->storedAs('data->>"$.alliance_id"')->index();
			$table->string('color')->storedAs('data->>"$.color"');
			$table->decimal(column: 'score', unsigned: true)->unsigned()->storedAs('data->>"$.score"');
			$table->boolean('vacation_mode')->storedAs('data->>"$.vacation_mode_turns" > 0');
			$table->integer('num_cities')->unsigned()->storedAs('data->>"$.num_cities"')->index();
			$table->integer('soldiers')->unsigned()->storedAs('data->>"$.soldiers"');
			$table->integer('tanks')->unsigned()->storedAs('data->>"$.tanks"');
			$table->integer('aircraft')->unsigned()->storedAs('data->>"$.aircraft"');
			$table->integer('ships')->unsigned()->storedAs('data->>"$.ships"');
			$table->integer('missiles')->unsigned()->storedAs('data->>"$.missiles"');
			$table->integer('nukes')->unsigned()->storedAs('data->>"$.nukes"');

			$table->index(['alliance_id', 'color', 'score', 'vacation_mode', 'soldiers', 'tanks', 'aircraft', 'ships'], 'nation_target_index');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::dropIfExists('nations');
	}
};
