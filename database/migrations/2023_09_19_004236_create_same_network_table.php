<?php

use App\Models\BotAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		$bot_accounts_table = (new BotAccount)->getTable();
		Schema::create('same_network', function(Blueprint $table) use ($bot_accounts_table) {
			$table->id();
			$table->foreignId('first_account_id')->constrained($bot_accounts_table)->cascadeOnDelete();
			$table->foreignId('second_account_id')->constrained($bot_accounts_table)->cascadeOnDelete();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::dropIfExists('same_network');
	}
};
