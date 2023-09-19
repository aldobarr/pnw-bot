<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::create('bot_accounts', function(Blueprint $table) {
			$table->id();
			$table->foreignId('email_id')->unique()->constrained();
			$table->string('password');
			$table->bigInteger('nation_id')->nullable()->unique();
			$table->string('vpn')->nullable()->unique();
			$table->string('api_key')->nullable();
			$table->boolean('verified')->default(false);
			$table->boolean('nation_created')->default(false);
			$table->boolean('tutorial_completed')->default(false);
			$table->boolean('built_first_project')->default(false);
			$table->boolean('changed_animal')->default(false);
			$table->boolean('completed_survey')->default(false);
			$table->boolean('player_controlled')->default(false);
			$table->boolean('banned')->default(false);
			$table->dateTime('last_login_at')->nullable()->default(null);
			$table->dateTime('next_login_at')->nullable()->default(null);
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::dropIfExists('bot_accounts');
	}
};
