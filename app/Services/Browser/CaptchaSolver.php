<?php

namespace App\Services\Browser;

use Illuminate\Support\Facades\Http;

class CaptchaSolver {
	public const MAX_ATTEMPTS = 30;
	public const ERROR_STRING = 'ERROR';
	public const BASE_ATTEMPT_URL = 'https://ocr.captchaai.com/in.php';
	public const BASE_RESULT_URL = 'https://ocr.captchaai.com/res.php';

	private $apiKey;
	private $userAgent;

	public function __construct() {
		$this->apiKey = config('browser.captcha');
		$this->userAgent = config('browser.user-agent');
	}

	private function getSleepSeconds($attempts): int {
		if ($attempts > 4) {
			return 25;
		}

		return pow(2, $attempts);
	}

	public function solve(string $key, string $page): ?string {
		$attempts = 1;
		$keep_trying = true;
		$attempt = Http::withQueryParameters([
			'key' => $this->apiKey,
			'method' => 'userrecaptcha',
			'googlekey' => $key,
			'pageurl' => $page,
			'json' => 1,
			'userAgent' => $this->userAgent
		])->timeout(5)->connectTimeout(5)->retry(static::MAX_ATTEMPTS, 25, null, false);
		$attempt = $attempt->get(static::BASE_ATTEMPT_URL);

		if (!$attempt->successful()) {
			return null;
		}

		$captcha_attempt = $attempt->object();
		if (empty($captcha_attempt) || empty($captcha_attempt->status) || $captcha_attempt->status != 1) {
			return null;
		}

		$key = static::ERROR_STRING;
		$captcha_id = $captcha_attempt->request;

		do {
			sleep($this->getSleepSeconds($attempts++));
			$result = Http::withQueryParameters([
				'key' => $this->apiKey,
				'action' => 'get',
				'id' => $captcha_id,
				'json' => 1
			])->timeout(5)->connectTimeout(5)->retry(static::MAX_ATTEMPTS, 0, null, false)->get(static::BASE_RESULT_URL);

			if (!$attempt->successful()) {
				continue;
			}

			$captcha_result = $result->object();
			if ($captcha_result->status != 1) {
				if (strcasecmp($captcha_result->request ?? '', 'CAPCHA_NOT_READY') !== 0) {
					return static::ERROR_STRING;
				}

				continue;
			}

			$keep_trying = false;
			$key = $captcha_result->request ?? static::ERROR_STRING;
		} while($keep_trying && $attempts < static::MAX_ATTEMPTS);

		return $key;
	}
}
