<?php

namespace App\Services\Browser;

use App\Enums\LoginStatus;
use App\Models\BotAccount;
use App\Services\BrowserService;
use Illuminate\Support\Str;

class LoginService extends BrowserService {
	use PoliticsAndWar;

	public function login(string $email, string $pass): bool {
		// visit homepage first
		$response = $this->parseHTML($this->client->get('/'));
		if ($this->checkLoginState($response) === LoginStatus::LOGGED_IN) {
			return true;
		}

		$this->client->get('/login/');
		$this->client->asForm()->post('/login/', [
			'email' => $email,
			'password' => $pass,
			'loginform' => 'Login'
		]);

		return $this->checkLoginState($this->parseHTML($this->client->get('/'))) === LoginStatus::LOGGED_IN;
	}

	public function logout(): void {
		$this->client->get('/logout/');
		$this->client->post('/logout/', ['logout' => 'Logout']);
		$this->clearCookies();
	}

	public function createAPIKey(BotAccount &$account): bool {
		$account_page = $this->parseHTML($this->client->get('/account/'));
		if (!$this->checkLoginState($account_page) === LoginStatus::LOGGED_IN) {
			if (!$this->login($account->email->login, $account->password)) {
				return false;
			}

			$account_page = $this->parseHTML($this->client->get('/account/'));
		}

		$api_forms = $account_page->querySelectorAll('#rightcolumn form[action="#7"]');
		if (empty($api_forms) || count($api_forms) < 3) {
			return false;
		}

		$form = [];
		$inputs = $api_forms[2]->getElementsByTagName('input');
		foreach ($inputs as $input) {
			$name = $input->getAttribute('name');
			if (empty($name)) {
				continue;
			}

			if (Str::contains($name, '[]')) {
				$name = str_replace('[]', '', $name);
				if (!isset($form[$name])) {
					$form[$name] = [];
				}

				$form[$name][] = $input->getAttribute('value') ?? '';
			} else {
				$form[$name] = $input->getAttribute('value') ?? '';
			}
		}

		$scopes_page = $this->parseHTML($this->client->asForm()->post('/account/#7', $form));
		$success = $scopes_page->getElementById('api_key_scopes_success');
		if (empty($success) || !Str::contains(strtolower($success->getAttribute('class')), 'alert-success')) {
			return false;
		}

		$form = [];
		$inputs = $api_forms[1]->getElementsByTagName('input');
		foreach ($inputs as $input) {
			$name = $input->getAttribute('name');
			if (empty($name)) {
				continue;
			}

			$form[$name] = $input->getAttribute('value') ?? '';
		}

		$allow_page = $this->parseHTML($this->client->asForm()->post('/account/#7', $form));
		$success = $allow_page->getElementById('api_key_whitelist_success');
		if (empty($success) || !Str::contains(strtolower($success->getAttribute('class')), 'alert-success')) {
			return false;
		}

		$api_key = trim($api_forms[0]->parentNode->parentNode->firstElementChild->getNodeValue() ?? '');
		if (empty($api_key)) {
			return false;
		}

		$nation_id = null;
		$all_links = $allow_page->querySelectorAll('#leftcolumn > ul.sidebar > a');
		foreach ($all_links as $link) {
			$url = $link->getAttribute('href') ?? '';
			if (Str::contains($url, 'nation/id=')) {
				$parts = parse_url($url);
				[, $id] = explode('=', $parts['path']);
				$nation_id = $id;
				break;
			}
		}

		if (empty($nation_id)) {
			return false;
		}

		$account->nation_id = $nation_id;
		$account->api_key = $api_key;
		$account->save();
		return true;
	}
}
