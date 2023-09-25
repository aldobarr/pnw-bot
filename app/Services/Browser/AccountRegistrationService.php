<?php

namespace App\Services\Browser;

use App\Enums\Resource;
use App\Mail\Contracts\Mail;
use App\Models\BotAccount;
use App\Models\Email;
use App\Models\Event;
use App\Models\NationName;
use App\Models\WorldCity;
use App\Services\BrowserService;
use App\Services\PoliticsAndWarAPIService;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use IvoPetkov\HTML5DOMDocument;
use IvoPetkov\HTML5DOMElement;

class AccountRegistrationService extends BrowserService {
	use PoliticsAndWar;

	private const TITLES = ['King', 'Emperor', 'Ruler', 'Duke', 'Queen', 'Empress', 'President', 'Prime Minister', 'Lord'];
	private const EMAIL_API_BASE_URL = 'https://api.mail.tm';
	private const USERNAME_MASTER_LIST = 'https://raw.githubusercontent.com/jeanphorn/wordlist/master/usernames.txt';
	private const NATION_CREATE_PAGE = '/nation/create/';
	private const PASSWORD_RESET_PAGE = '/login/reset/';
	private const MAX_CHECK_ATTEMPTS = 10;

	private $emailAPIToken = '';
	private $usernames = [];
	private $usernamesCount = 0;

	public function createAccount(string $vpn, bool $use_tempmail): ?BotAccount {
		$email = Email::getUnusedEmail($use_tempmail);
		if (empty($email)) {
			return null;
		}

		$mail_handler = $email->getMailHandler();

		try {
			$mail_handler->getAllInboxes();
		} catch (\Throwable) {
			return null;
		}

		$account = new BotAccount;
		$account->email()->associate($email);
		$account->password = Str::random(16);
		$account->vpn = $vpn;
		$account->save();

		if (!$this->createPNWAccount($account)) {
			return null;
		}

		sleep(random_int(10, 20));
		if (!$this->verifyEmail($mail_handler)) {
			return null;
		}

		$account->verified = true;
		$account->save();

		if (!$this->createNation($account)) {
			return null;
		}

		if ($this->completeTutorial()) {
			$account->tutorial_completed = true;
			$account->save();
		}

		if ($this->buyFirstProject()) {
			$account->built_first_project = true;
			$account->save();
		}

		return $account;
	}

	private function createPNWAccount(BotAccount $account): bool {
		$this->clearCookies();
		$this->client->get('/');
		$reg_page = $this->parseHTML($this->client->get('/register/'));

		$form = [];
		$right_column = $reg_page->getElementById('rightcolumn');
		$form_html = $right_column->querySelector('form');
		$inputs = $form_html->getElementsByTagName('input');
		foreach ($inputs as $input) {
			$name = $input->getAttribute('name');
			$form[$name] = $input->getAttribute('value') ?? '' ?: '';
			if (strcasecmp($name, 'email') === 0 || strcasecmp($name, 'email2') === 0) {
				$form[$name] = $account->email->login;
			}
		}

		$page = $this->parseHTML($this->client->asForm()->post($form_html->getAttribute('action') ?? '/register/', $form));
		$alerts = $page->querySelectorAll('div.alert-success');
		foreach ($alerts as $alert) {
			$text = strtolower($alert->getTextContent());
			if (Str::contains($text, 'has been created')) {
				Event::logEvent('account_registered', $account, $form);
				return true;
			}
		}

		Event::logEvent('failed_registration_form', $account, $form, $page->saveHTML());
		return false;
	}

	public function createNation(BotAccount $account): bool {
		$nation_page = $this->parseHTML($this->client->get(static::NATION_CREATE_PAGE));
		$nation_form = $nation_page->getElementById('createNationForm');
		if (empty($nation_form)) {
			return false;
		}

		$form = [];
		$inputs = $nation_form->getElementsByTagName('input');
		foreach ($inputs as $input) {
			$name = $input->getAttribute('name');
			$form[$name] = $input->getAttribute('value') ?? '';
		}

		$capital = WorldCity::whereRaw('LENGTH(`name_normalized`) > 4')->inRandomOrder()->first();
		$account->capital_id = $capital->id;
		$account->save();

		$form['pass1'] = $form['pass2'] = $account->password;
		$form['nation'] = $this->getNewName();
		$form['capital'] = $capital->name_normalized;
		$form['title'] = static::TITLES[random_int(0, count(static::TITLES) - 1)];
		$form['leader'] = $this->getNewName(true, 'leader');

		$selects = $nation_form->getElementsByTagName('select');
		foreach ($selects as $select) {
			$options = [];
			$name = $select->getAttribute('name');
			$select_options = $select->getElementsByTagName('option');
			foreach ($select_options as $option) {
				$options[] = $option->getAttribute('value');
			}

			$form[$name] = $options[random_int(0, count($options) - 1)];
		}

		$form = $this->getMapFromCapital($form, $capital);
		$nation = $this->parseHTML($this->client->asForm()->post(static::NATION_CREATE_PAGE, $form));
		$right_column = $nation->getElementById('rightcolumn');
		$all_ps = $right_column->getElementsByTagName('p');
		foreach ($all_ps as $p) {
			$text = strtolower($p->getTextContent());
			if (Str::contains($text, 'successfully created your nation')) {
				$account->nation_created = true;
				$account->save();

				Event::logEvent('nation_created', $account, $form);

				return true;
			}
		}

		Event::logEvent('failed_nation_form', $account, $form, $nation->saveHTML());
		return false;
	}

	public function resetPassword(BotAccount $account): bool {
		$reset_page = $this->parseHTML($this->client->get(static::PASSWORD_RESET_PAGE));
		$form_element = $reset_page->getElementById('rightcolumn')->getElementsByTagName('form')[0] ?? null;
		if (empty($form_element)) {
			return false;
		}

		$form = [];
		$inputs = $form_element->getElementsByTagName('input');
		foreach ($inputs as $input) {
			$form[$input->getAttribute('name')] = $input->getAttribute('value') ?? '';
		}

		$form['email'] = $account->email->login;
		$mail_handler = $account->email->getMailHandler();

		try {
			$mail_handler->getAllInboxes();
		} catch (\Throwable) {
			return null;
		}

		$response = $this->parseHTML($this->client->asForm()->post(static::PASSWORD_RESET_PAGE, $form));
		if ($this->doesNotExist($response)) {
			if ($this->createPNWAccount($account)) {
				$result = $this->verifyEmail($mail_handler);
				if (!$result) {
					return false;
				}

				$account->verified = true;
				$account->save();
				return true;
			}

			return false;
		}

		$mailboxes = $mail_handler->getAllInboxes();
		if (empty($mailboxes)) {
			return false;
		}

		$reset_message = null;
		foreach ($mailboxes as $mailbox) {
			$mail_handler->setMailbox($mailbox['fullpath']);
			$emails = $mail_handler->getPNWMessages();
			foreach ($emails as $email_id) {
				$message = $mail_handler->getMail($email_id);
				if (!$message->isSeen && Str::contains(strtolower($message->subject), 'password reset')) {
					$mail_handler->markRead($email_id);
					$reset_message = $message;
					break;
				}
			}

			if (!empty($reset_message)) {
				break;
			}
		}

		if (empty($reset_message) || empty($reset_message->textHtml)) {
			return false;
		}

		$reset_link = $this->detectResetPasswordLink($reset_message->textHtml);
		if (empty($reset_link)) {
			return false;
		}

		$link_parts = parse_url($reset_link);
		if (empty($link_parts) || empty($link_parts['path'])) {
			return false;
		}

		$reset_page = $this->parseHTML($this->client->get($link_parts['path']));
		$reset_form = $reset_page->getElementById('rightcolumn')->getElementsByTagName('form')[0] ?? null;
		if (empty($reset_form)) {
			return false;
		}

		$form = [];
		$action = $reset_form->getAttribute('action');
		$inputs = $reset_form->getElementsByTagName('input');
		foreach ($inputs as $input) {
			$form[$input->getAttribute('name')] = $input->getAttribute('value') ?? '';
		}

		$form['pass1'] = $form['pass2'] = Str::random(16);
		$account->password = $form['pass1'];

		$reset_page = $this->parseHTML($this->client->asForm()->post($action, $form));
		$right_column = $reset_page->getElementById('rightcolumn');
		if (empty($right_column)) {
			return false;
		}

		$ps = $right_column->getElementsByTagName('p');
		foreach ($ps as $p) {
			$text = strtolower($p->getTextContent());
			if (Str::contains($text, 'successfully updated your password')) {
				$account->save();
				return true;
			}
		}

		return false;
	}

	private function doesNotExist(HTML5DOMDocument $page): bool {
		$error = $page->querySelector('#rightcolumn div.alert-danger');
		if (empty($error)) {
			return false;
		}

		$text = strtolower(trim($error->getTextContent()));
		if (empty($text)) {
			return false;
		}

		return Str::contains($text, 'address does not exist');
	}

	public function buyFirstProject(): bool {
		if (!$this->buyFoodForProject()) {
			return false;
		}

		$projects_page = $this->parseHTML($this->client->get('/nation/projects/'));
		$projects_form = $projects_page->getElementById('project_form');
		if (empty($projects_form)) {
			return false;
		}

		$form = ['buy_rpc_np' => 'Construct'];
		$input = $projects_form->querySelector('input[name="token"]');
		if (!empty($input)) {
			$form[$input->getAttribute('name')] = $input->getAttribute('value') ?? '';
		}

		$projects_page = $this->parseHTML($this->client->asForm()->post('/nation/projects/', $form));
		$success_div = $projects_page->querySelector('#rightcolumn > div.alert-success');
		if (!empty($success_div) && Str::contains(strtolower($success_div->getTextContent()), 'successfully built the national project')) {
			return true;
		}

		return false;
	}

	private function buyFoodForProject(int $attempts = 5): bool {
		$simulator = app(AccountSimulationService::class);
		$simulator->setCookies($this->getCookies());

		do {
			$result = $simulator->buyResource(Resource::FOOD->value, 1000);
			if ($result) {
				return true;
			}
		} while (--$attempts > 0);

		return false;
	}

	private function detectResetPasswordLink(string $message): ?string {
		$body = new HTML5DOMDocument;
		$body->loadHTML($message, HTML5DOMDocument::ALLOW_DUPLICATE_IDS);

		$links = $body->getElementsByTagName('a');
		foreach ($links as $link) {
			$url = $link->getAttribute('href');
			if (Str::contains($url, 'login/reset/email')) {
				return $url;
			}
		}

		return null;
	}

	public function verifyEmail(Mail $email): bool {
		$mailboxes = $email->getAllInboxes();
		if (empty($mailboxes)) {
			return false;
		}

		try {
			$verify_link = null;
			foreach ($mailboxes as $mailbox) {
				$email->setMailbox($mailbox['fullpath']);
				$emails = $email->getPNWMessages();
				foreach ($emails as $email_id) {
					$message = $email->getMail($email_id);
					if (!$message->isSeen && Str::contains(strtolower($message->subject), 'account activation')) {
						$email->markRead($email_id);
						$verify_link = $this->detectVerificationLink($message->textHtml);
						if (!empty($verify_link)) {
							break;
						}
					}
				}
			}
		} catch (\Throwable) {
			return false;
		}

		if (empty($verify_link)) {
			return false;
		}

		$link_parts = parse_url($verify_link);
		if (empty($link_parts) || empty($link_parts['path'])) {
			return false;
		}

		$link_query = [];
		parse_str($link_parts['query'], $link_query);
		$this->client->get($link_parts['path'], $link_query);
		$page = $this->parseHTML($this->client->get('/nation/create/'));
		$form = $page->getElementById('createNationForm');
		return !empty($form);
	}

	private function detectVerificationLink(string $message): ?string {
		$body = new HTML5DOMDocument;
		$body->loadHTML($message, HTML5DOMDocument::ALLOW_DUPLICATE_IDS);

		$links = $body->getElementsByTagName('a');
		foreach ($links as $link) {
			$url = $link->getAttribute('href');
			if (Str::contains($url, 'id=') && Str::contains($url, 'key=')) {
				return $url;
			}
		}

		return null;
	}

	public function getMapFromCapital(array $form, WorldCity $capital): array {
		$bounds = PoliticsAndWarAPIService::getBoundingBox($capital->lat, $capital->lng, 8);
		$form['latitude'] = $capital->lat;
		$form['longitude'] = $capital->lng;
		$form['polygonVertices'] = json_encode([
			$bounds['northwest'],
			$bounds['northeast'],
			$bounds['southeast'],
			$bounds['southwest'],
		]);

		return $form;
	}

	public function completeTutorial(): bool {
		$home_page = $this->parseHTML($this->client->get('/'));
		$intro_button = $home_page->querySelector('button[data-continue-tutorial="introduction"]');
		$parent = $intro_button->parentNode->parentNode;
		if (!($parent instanceof HTML5DOMElement)) {
			return false;
		}

		$parent_html = $parent->outerHTML ?? '';
		$pattern = '/\$\.ajax\({(.*?)}\);/s';
		if (!preg_match($pattern, $parent_html, $matches)) {
			return false;
		}

		$ajax_string = $matches[0]; // The matched string
		$data_start = strpos($ajax_string, 'data: ') + strlen('data: ');
		$data_end = strrpos($ajax_string, '}');
		$data_block = trim(substr($ajax_string, $data_start, $data_end - $data_start));
		$data_block = substr($data_block, 0, -1);
		$lines = explode(PHP_EOL, $data_block);
		$data_block = [];

		foreach ($lines as $line) {
			if (Str::contains($line, 'step')) {
				continue;
			}

			if (Str::contains($line, ':')) {
				$line = '"' . str_replace(':', '":', trim($line));
			}

			$data_block[] = str_replace('\'', '"', $line);
		}

		$data_block = implode(PHP_EOL, $data_block);
		['account_id' => $account_id, 'api_key' => $key] = json_decode(trim($data_block), true);

		$tutorial = [];
		$tabs = $home_page->querySelector('div[data-tab-content="introduction"]')->previousElementSibling->getElementsByTagName('div');
		foreach ($tabs as $tab) {
			$name = $tab->getAttribute('data-tab');
			$tutorial[$name] = [];

			$steps = $home_page->querySelector('div[data-category="' . $name . '"]')->getElementsByTagName('div');
			foreach ($steps as $step) {
				$data = $step->getAttribute('data-step');
				if (empty($data)) {
					continue;
				}

				$tutorial[$name][] = $data;
			}

			array_pop($tutorial[$name]);
		}

		foreach ($tutorial as $category => $steps) {
			$domain = 'politicsandwar.com';
			$cookies = $this->client->getOptions()['cookies'];
			$cookies->setCookie(new SetCookie([
				'Domain' => $domain,
				'Name' => 'tutorial-tab',
				'Value' => $category,
				'Discard' => false,
				'Expires' => now()->addDays(30)->getTimestamp(),
				'Secure' => true,
				'HttpOnly' => true
			]));

			$this->client->withOptions(['cookies' => $cookies]);

			foreach ($steps as $step) {
				if (!$this->claimTutorialReward($account_id, $key, $category, $step)) {
					return false;
				}

				usleep(250000 + random_int(0, 2000000));
			}
		}

		return true;
	}

	private function claimTutorialReward(string $account_id, string $key, string $category, string $step): bool {
		$domain = 'politicsandwar.com';
		$cookies = $this->client->getOptions()['cookies'];
		$cookies->setCookie(new SetCookie([
			'Domain' => $domain,
			'Name' => 'tutorial_' . $category,
			'Value' => $step,
			'Discard' => false,
			'Expires' => now()->addDays(30)->getTimestamp(),
			'Secure' => true,
			'HttpOnly' => true
		]));

		$this->client->withOptions(['cookies' => $cookies]);
		$response = $this->client->asForm()->post('/api/tutorial-reward.php', [
			'account_id' => $account_id,
			'category' => $category,
			'step' => $step,
			'api_key' => $key
		]);

		if (!$response->successful()) {
			return $response->status() === 500;
		}

		$data = $response->body();
		return Str::startsWith(strtolower($data), 'current step:') || Str::startsWith(strtolower($data), 'step not active');
	}

	public function getNewName(bool $check_unique = true, string $type = 'nation'): string {
		$attempts = 0;
		$name = (strcmp($type, 'nation') === 0 ?
			$this->getRandomNationName() :
			(
				strcmp($type, 'city') === 0 ?
					$this->getRandomCityName() :
					ucwords(strtolower($this->getRandomUsername()))
			)
		);

		if (!$check_unique) {
			return $name;
		}

		while ($attempts++ < static::MAX_CHECK_ATTEMPTS) {
			$check_name = $this->client->get('/api/check_name.php', ['type' => $type, 'name' => $name]);
			if (!$check_name->successful()) {
				sleep(1);
				continue;
			}

			$result = $this->parseHTML('<html><body>' . $check_name->body() . '</body></html>');
			$divs = $result->getElementsByTagName('div');
			if (empty($divs)) {
				continue;
			}

			$class = strtolower($divs[0]->getAttribute('class'));
			if (Str::contains($class, 'alert-success')) {
				return $name;
			}
		}

		return '';
	}

	private function getUniqueEmail(string $domain): string {
		$email = null;

		do {
			$name = $this->getRandomUsername() . random_int(1, 99);
			$email = $name . '@' . $domain;
		} while (BotAccount::where('email', $email)->exists());

		return $email;
	}

	public function getRandomUsername(): string {
		if (empty($this->usernames)) {
			$this->parseUsernameMasterlist();
		}

		$location = random_int(0, $this->usernamesCount - 1);
		$username = $this->usernames[$location];
		$name = preg_replace('/[^A-Za-z0-9\-&\s]/', '', $username);
		if (strcmp($username, $name) !== 0) {
			$this->usernames[$location] = $name;
		}

		if (strlen($name) < 5) {
			unset($this->usernames[$location]);
			$this->usernames = array_values($this->usernames);
			$this->usernamesCount = count($this->usernames);
			Cache::put('account.username.masterlist', $this->usernames);
			return $this->getRandomUsername();
		}

		return $name;
	}

	public function getRandomNationName(): string {
		return NationName::inRandomOrder()->value('name');
	}

	public function getRandomCityName(?string $country_iso = null): string {
		return WorldCity::where(function(Builder $query) use ($country_iso) {
			if (!is_null($country_iso)) {
				$query->where('country_iso', $country_iso);
			}
		})->inRandomOrder()->value('name');
	}

	private function parseUsernameMasterlist(): void {
		if (Cache::has('account.username.masterlist')) {
			$this->usernames = Cache::get('account.username.masterlist');
			$this->usernamesCount = count($this->usernames);
			return;
		}

		$raw_list = Http::get(static::USERNAME_MASTER_LIST)->body();
		$raw_lines = array_map('trim', explode(PHP_EOL, $raw_list));
		$this->usernames = array_values(array_filter($raw_lines, fn($value) => !empty($value) && strlen($value) >= 5));
		$this->usernamesCount = count($this->usernames);
		Cache::put('account.username.masterlist', $this->usernames);
	}
}
