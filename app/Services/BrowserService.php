<?php

namespace App\Services;

use App\Services\Browser\CaptchaSolver;
use IvoPetkov\HTML5DOMDocument;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use IvoPetkov\HTML5DOMElement;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class BrowserService {
	protected string $userAgent;
	protected PendingRequest $client;
	protected RequestInterface $lastRequest;
	protected HTML5DOMDocument $lastPage;
	protected $skip = false;

	private string $baseUrl;
	private int $connectionTimeout;
	private int $timeout;
	private CookieJar $cookieJar;

	public function __construct(string $base_url = '', int $timeout = 30, int $connection_timeout = 60) {
		$this->userAgent = config('browser.user-agent');
		$this->baseUrl = $base_url;
		$this->connectionTimeout = $connection_timeout;
		$this->timeout = $timeout;
		$this->cookieJar = new CookieJar;
		$this->resetClient();
	}

	public function isRandomCheck(HTML5DOMDocument $page): bool {
		$meta_tags = $page->getElementsByTagName('meta');
		foreach ($meta_tags as $meta) {
			$content = $meta->getAttribute('content') ?? '';
			if (empty($content) || !Str::contains($content, '.com') || !Str::contains($content, '/human/')) {
				continue;
			}

			return true;
		}

		return false;
	}

	public function parseHTML(string $body): HTML5DOMDocument {
		$dom = new HTML5DOMDocument;
		$dom->loadHTML($body, HTML5DOMDocument::ALLOW_DUPLICATE_IDS);
		return $dom;
	}

	public function getFormInputs(HTML5DOMElement $form_element): array {
		$form = [];
		$inputs = $form_element->getElementsByTagName('input');
		foreach ($inputs as $input) {
			$name = $input->getAttribute('name') ?? '';
			if (empty($name)) {
				continue;
			}

			$value = $input->getAttribute('value') ?? '';
			if (Str::contains($name, '[]')) {
				if (!isset($form[$name])) {
					$form[$name] = [];
				}

				$form[$name][] = $value;
			} else {
				$form[$name] = $value;
			}
		}

		$selects = $form_element->getElementsByTagName('select');
		foreach ($selects as $select) {
			$name = $select->getAttribute('name') ?? '';
			if (empty($name)) {
				continue;
			}

			$value = '';
			$options = $select->getElementsByTagName('option');
			if (!empty($options)) {
				$value = $options[0]->getAttribute('value');
				foreach ($options as $option) {
					if ($option->hasAttribute('selected')) {
						$value = $option->getAttribute('value') ?? '';
						break;
					}
				}
			}

			if (Str::contains($name, '[]')) {
				if (!isset($form[$name])) {
					$form[$name] = [];
				}

				$form[$name][] = $value;
			} else {
				$form[$name] = $value;
			}
		}

		$text_areas = $form_element->getElementsByTagName('textarea');
		foreach ($text_areas as $text_area) {
			$name = $text_area->getAttribute('name') ?? '';
			if (empty($name)) {
				continue;
			}

			$value = $text_area->getTextContent() ?? '';
			if (Str::contains($name, '[]')) {
				if (!isset($form[$name])) {
					$form[$name] = [];
				}

				$form[$name][] = $value;
			} else {
				$form[$name] = $value;
			}
		}

		return $form;
	}

	public function clearCookies(): void {
		$this->cookieJar = new CookieJar;
		$this->client->withOptions([
			'cookies' => $this->cookieJar
		]);
	}

	public function getCookies(): ?CookieJar {
		return $this->client->getOptions()['cookies'] ?? null;
	}

	public function setCookies(CookieJar $cookies): void {
		$this->cookieJar = $cookies;
		$this->client->withOptions([
			'cookies' => $cookies
		]);
	}

	protected function detectCaptcha(HTML5DOMDocument $page, string $url, array $form = []): array {
		$captcha_key = $this->getCaptchaKey($page);
		if ($captcha_key !== null) {
			$captcha_solver = app(CaptchaSolver::class);
			$form['g-recaptcha-response'] = $captcha_solver->solve($captcha_key, $url);
			if (strcmp($form['g-recaptcha-response'], CaptchaSolver::ERROR_STRING) === 0) {
				return [];
			}
		}

		return $form;
	}

	protected function getCaptchaKey(HTML5DOMDocument $page): ?string {
		$recaptcha = $page->querySelector('.g-recaptcha');
		if (empty($recaptcha)) {
			return null;
		}

		return $recaptcha->getAttribute('data-sitekey');
	}

	protected function resetClient(): void {
		$this->client = Http::retry(3, 1250, throw: false)->withUserAgent($this->userAgent)->withOptions([
			'base_uri' => $this->baseUrl,
			'timeout' => $this->timeout,
			'connect_timeout' => $this->connectionTimeout,
			'cookies' => $this->cookieJar
		])->withRequestMiddleware(function(RequestInterface $request) {
			if (
				!empty($this->lastPage) &&
				!empty($this->lastRequest) &&
				strcasecmp($request->getMethod(), 'POST') === 0 &&
				!Str::contains(strtolower($request->getUri()->getPath()), 'human')
			) {
				$form = $this->detectCaptcha($this->lastPage, $this->lastRequest->getUri());
				if (!empty($form)) {
					$new_body = $body = $request->getBody()->getContents();
					if (Str::contains($request->getHeader('Content-Type')[0], 'form-urlencoded')) {
						$new_body = http_build_query($form) . (!empty($body) ? ('&' . $body) : '');
					} else if (Str::contains($request->getHeader('Content-Type')[0], 'json')) {
						$new_body = json_encode(array_merge($form, json_decode($body, true)));
					}

					$this->lastRequest = $request->withBody(\GuzzleHttp\Psr7\Utils::streamFor($new_body));
					return $this->lastRequest;
				}
			}

			$this->lastRequest = $request;
			return $request;
		})->withResponseMiddleware(function(ResponseInterface $response) {
			$this->resetClient();
			$page = $this->parseHTML($response->getBody());
			$intended_uri = $this->lastRequest->getUri();
			if (strcasecmp($this->lastRequest->getMethod(), 'GET') === 0 && $this->isRandomCheck($page)) {
				$this->skip = true;
				$human_page = $this->parseHTML($this->client->get('/human/'));
				$form = $this->detectCaptcha($human_page, $this->lastRequest->getUri());
				if (!empty($form)) {
					$right_column = $human_page->getElementById('rightcolumn');
					$form_html = $right_column->querySelector('form');
					$inputs = $form_html->getElementsByTagName('input');
					foreach ($inputs as $input) {
						$name = $input->getAttribute('name');
						$form[$name] = $input->getAttribute('value') ?? '' ?: '';
					}

					$check_page = $this->parseHTML($this->client->asForm()->post($form_html->getAttribute('action') ?? '' ?: '/human/', $form));
					$success = $check_page->querySelector('#rightcolumn div.alert-success');
					if (!empty($success)) {
						$next = $this->client->get($intended_uri);
						$this->lastPage = $this->parseHTML($next);
						return $next->toPsrResponse();
					}
				}
			}

			$this->lastPage = $page;
			return $response;
		});
	}
}
