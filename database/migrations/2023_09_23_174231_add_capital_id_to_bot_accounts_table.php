<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::table('bot_accounts', function(Blueprint $table) {
			$table->foreignId('capital_id')->default(1)->after('api_key')->references('id')->on('world_cities')->constrained();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::table('bot_accounts', function(Blueprint $table) {
			$table->dropForeign(['capital_id']);
			$table->dropColumn('capital_id');
		});
	}
};
