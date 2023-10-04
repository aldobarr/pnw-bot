<?php

namespace App\Services\Browser\AccountSimulation;

use App\Enums\Resource;
use App\Services\Browser\AccountSimulationService;
use App\Services\LocationService;
use Illuminate\Support\Str;

trait NationSimulation {
	public function clearNotifications(): void {
		$home_page = $this->parseHTML($this->client->get('/'));
		$sidebar = $home_page->querySelector('#leftcolumn > ul.sidebar');
		if (empty($sidebar)) {
			return;
		}

		$links = $sidebar->getElementsByTagName('a');
		foreach ($links as $link) {
			$url = $link->getAttribute('href') ?? '';
			if (!Str::contains($url, '/inbox') && !Str::contains($url, '/nation/notifications')) {
				continue;
			}

			$counter = $link->querySelector('span');
			if (empty($counter) || !Str::contains(strtolower($counter->getAttribute('class') ?? ''), 'counter')) {
				continue;
			}

			$parts = parse_url($url);
			if (empty($parts) || empty($parts['path'])) {
				continue;
			}

			$this->visitNotificationPage($parts['path']);
		}
	}

	private function visitNotificationPage(string $path): void {
		$notif_page = $this->parseHTML($this->client->get($path));
		if (empty($notif_page) || !Str::contains($path, '/inbox')) {
			return;
		}

		$notif_form = [];
		$search_form = $notif_page->querySelector('#rightcolumn form');
		if (empty($search_form)) {
			return;
		}

		$inputs = $search_form->getElementsByTagName('input');
		foreach ($inputs as $input) {
			$name = $input->getAttribute('name') ?? '';
			if (empty($name) || strcasecmp($name, 'backpage') === 0 || strcasecmp($name, 'gopage') === 0) {
				continue;
			}

			$notif_form[$name] = $input->getAttribute('value') ?? '';
		}

		$notif_form['maximum'] = 100;
		$inbox_page = $this->parseHTML($this->client->asForm()->get('/index.php', $notif_form));
		if (empty($inbox_page)) {
			return;
		}

		$messages = $inbox_page->querySelector('#rightcolumn form table.nationtable');
		if (empty($messages)) {
			return;
		}

		$form = [];
		$inputs = $messages->getElementsByTagName('input');
		foreach ($inputs as $input) {
			$name = $input->getAttribute('name') ?? '';
			if (empty($name)) {
				continue;
			}

			$value = $input->getAttribute('value') ?? '';
			if (Str::contains($name, '[]')) {
				$name = str_replace(['[', ']'], '', $name);
				if (!array_key_exists($name, $form)) {
					$form[$name] = [];
				}

				$form[$name][] = $value;
			} else {
				$form[$name] = $value;
			}
		}

		$inputs = $messages->nextElementSibling?->getElementsByTagName('input');
		if (!empty($inputs)) {
			foreach ($inputs as $input) {
				$name = $input->getAttribute('name') ?? '';
				if (empty($name)) {
					continue;
				}

				$form[$name] = $input->getAttribute('value') ?? '';
			}
		}

		$form['markas'] = 'read';
		$this->client->asForm()->post('index.php?' . http_build_query($notif_form), $form);
	}

	private function checkResearchSurvey(\Psr\Http\Message\ResponseInterface $response): \Psr\Http\Message\ResponseInterface {
		if ($this->account->completed_survey) {
			return $response;
		}

		$page = $this->parseHTML($response->getBody());
		$survey_modal = $page?->getElementById('researchsurveymodal');
		if (empty($survey_modal)) {
			return $response;
		}

		$whois = LocationService::getLocation();
		if ($whois === null) {
			return $response;
		}

		$research_script = '';
		$scripts = $page->getElementsByTagName('script');
		foreach ($scripts as $script) {
			$html = $script->innerHTML;
			if (Str::contains($html, 'saveResearch')) {
				$research_script = $html;
				break;
			}
		}

		if (empty($research_script)) {
			return $response;
		}

		$api_key_string = 'api_key=';
		$find_key_location = stripos($research_script, $api_key_string);
		if ($find_key_location === false) {
			return $response;
		}

		$find_end_key_location = stripos($research_script, '",', $find_key_location);
		if ($find_end_key_location === false) {
			return $response;
		}

		$search_str_len = strlen($api_key_string);
		$api_key = substr($research_script, $find_key_location + $search_str_len, $find_end_key_location - ($find_key_location + $search_str_len));
		if (empty($api_key)) {
			return $response;
		}

		$form = [
			'action' => 'save',
			'account_id' => $this->account->nation_id,
			'ip' => $whois->ip,
			'birthyear' => random_int(1980, now()->subYears(20)->year),
			'gender' => random_int(1, 100) < 98 ? 'Male': 'Female',
			'country' => $whois->country_code,
			'device' => 'Desktop/Laptop',
			'api_key' => $api_key
		];

		$this->account->completed_survey = true; // Prevent next client response middleware from doing anything
		$api_response = $this->client->asForm()->post('/api/researchsurvey.php', $form);
		$this->account->completed_survey = false; // Reset value

		if (!$api_response->successful()) {
			return $response;
		}

		if (strcasecmp($api_response->body(), 'success') !== 0) {
			return $response;
		}

		$this->account->completed_survey = true;
		$this->account->save();
		return $response;
	}

	private function editNation(string $key, string $value): bool {
		$edit_page = $this->parseHTML($this->client->get('/nation/edit/'));
		$edit_form = $edit_page?->querySelector('#rightcolumn form');
		if (empty($edit_form)) {
			return false;
		}

		$form = $this->getFormInputs($edit_form);
		if (!array_key_exists($key, $form)) {
			return false;
		}

		$form[$key] = $value;
		unset($form['flagUpload'], $form['currency_image'], $form['nation_animal_image']);
		$multi_part_client = $this->client->attach('flagUpload', ' ')->attach('currency_image', ' ')->attach('nation_animal_image', ' ');

		$results_page = $this->parseHTML($multi_part_client->post('/nation/edit/#cosmetic_id_msg', $form));
		if (empty($results_page)) {
			return false;
		}

		$success = $results_page->querySelector('#rightcolumn p.alert-success');
		if (empty($success)) {
			return false;
		}

		return Str::contains(strtolower(trim($success->getTextContent())), 'successfully updated your nation');
	}

	public function depositResources(): bool {
		$deposits = [];
		$resources = Resource::casesExcept(Resource::CREDITS);
		$this->loadNationSimData();

		foreach ($resources as $resource) {
			$keep_amount = $resource->keepAmount();
			if ($this->nation->{$resource->value} > $keep_amount) {
				$deposits[$resource->value] = $this->nation->{$resource->value} - $keep_amount;
			}
		}

		if (empty($deposits)) {
			return true;
		}

		$url = '/alliance/id=' . AccountSimulationService::ALLIANCE_ID . '&display=bank';
		$bank_page = $this->parseHTML($this->client->get($url));
		$deposit_form = $bank_page?->querySelector('#rightcolumn form');
		if (empty($deposit_form) || !Str::contains(strtolower(trim($deposit_form->innerHTML)), 'make a deposit')) {
			return false;
		}

		$url .= '#withdrawal-result';
		$form = $this->getFormInputs($deposit_form);
		foreach ($deposits as $resource => $amount) {
			$form['dep' . $resource] = $amount;
		}

		$deposit_page = $this->parseHTML($this->client->asForm()->post($url, $form));
		if (empty($deposit_page)) {
			return false;
		}

		$success = $deposit_page->querySelectorAll('#rightcolumn div.alert-success');
		if (empty($success)) {
			return false;
		}

		foreach ($success as $div) {
			if (Str::contains(strtolower(trim($div->getTextContent())), 'successfully made a deposit')) {
				return true;
			}
		}

		return false;
	}
}