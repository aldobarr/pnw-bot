<?php

use App\Enums\MailHandler;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::create('emails', function(Blueprint $table) {
			$table->id();
			$table->string('login')->unique();
			$table->string('password');
			$table->enum('type', array_map(fn($val) => $val->name, MailHandler::cases()));
			$table->string('recovery')->nullable();
			$table->text('additional_data')->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::dropIfExists('emails');
	}
};
