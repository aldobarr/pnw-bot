<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::create('event_logs', function(Blueprint $table) {
			$table->id();
			$table->foreignId('account_id')->nullable()->references('id')->on('bot_accounts')->constrained()->cascadeOnDelete();
			$table->string('name');
			$table->longText('payload');
			$table->longText('secondary_payload');
            $table->longText('exception');
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::dropIfExists('event_logs');
	}
};
