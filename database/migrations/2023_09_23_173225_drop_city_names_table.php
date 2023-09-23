<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
	private $cityMigration;

	public function __construct() {
		$this->cityMigration = include(__DIR__ . DIRECTORY_SEPARATOR . '2023_09_11_154848_create_city_names_table.php');
	}

	/**
	 * Run the migrations.
	 */
	public function up(): void {
		$this->cityMigration->down();
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		$this->cityMigration->up();
	}
};
