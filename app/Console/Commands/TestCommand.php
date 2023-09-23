<?php

namespace App\Console\Commands;

use App\Enums\MailHandler;
use App\Enums\Resource;
use App\Enums\WarType;
use App\Mail\MailFactory;
use App\Models\BotAccount;
use App\Models\Email;
use App\Models\Nation;
use App\Models\NationName;
use App\Models\WorldCity;
use App\Services\Browser\AccountRegistrationService;
use App\Services\Browser\AccountSimulationService;
use App\Services\Browser\CaptchaSolver;
use App\Services\Browser\LoginService;
use App\Services\PoliticsAndWarAPIService;
use App\Services\VPNService;
use Ballen\Distical\Calculator;
use Ballen\Distical\Entities\LatLong;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use IvoPetkov\HTML5DOMDocument;
use Location\Bearing\BearingEllipsoidal;
use Location\Coordinate;
use Location\Distance\Vincenty;
use Location\Factory\BoundsFactory;

class TestCommand extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'run:test';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'App testing';

	/**
	 * Execute the console command.
	 */
	public function handle(LoginService $login_service, VPNService $vpn, AccountSimulationService $simulator, AccountRegistrationService $reg): void {
		$this->info(BotAccount::where('next_login_at', '<=', now())->count());
	}
}
