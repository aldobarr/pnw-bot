<?php

namespace App\Mail\Handlers;

use App\Mail\Contracts\Mail;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class Secmail extends Mail {
	protected PendingRequest $secMailClient;

	public function __construct(string $login, string $password) {
		[$username, $password] = explode('@', $login);
		$this->login = $username;
		$this->password = $password;
		$this->secMailClient = Http::withOptions([
			'base_uri' => 'https://www.1secmail.com/api/v1/',
		]);
	}

	public function getConnectionString(): string {
		return '';
	}

	public function getAllInboxes(): array {
		return [['fullpath' => '']];
	}

	public function setMailbox(string $fullpath): void {}

	public function markRead(int|SecmailMessage $id): void {}

	public function getMailboxMessages(string $criteria = ''): array {
		$response = $this->secMailClient->get('/', ['action' => 'getMessages', 'login' => $this->login, 'domain' => $this->password]);
		if (!$response->successful()) {
			return [];
		}

		$data = $response->object();
		foreach ($data as $key => $message) {
			$data[$key] = new SecmailMessage($message->id, $message->from, $message->subject, $message->date, $this);
		}

		return $data;
	}

	public function getPNWMessages(): array {
		return array_filter($this->getMailboxMessages(), fn($message) => stristr($message->subject, 'politics') !== false && stristr($message->subject, 'war') !== false);
	}

	public function getMessage(int $id): object {
		$response = $this->secMailClient->get('/', ['action' => 'readMessage', 'login' => $this->login, 'domain' => $this->password, 'id' => $id]);
		if (!$response->successful()) {
			return (object)[];
		}

		return $response->object();
	}

	public static function getDomains(): array {
		$response = (new static('1@2', ''))->secMailClient->get('/', ['action' => 'getDomainList']);
		if (!$response->successful()) {
			return [];
		}

		return $response->json();
	}
}

class SecmailMessage {
	public int $id;
	public string $from;
	public string $subject;
	public bool $isSeen = false;
	public Carbon $date;
	private Secmail $client;
	private string $textHtml = '';

	public function __construct(int $id, string $from, string $subject, string $date, Secmail &$client) {
		$this->id = $id;
		$this->from = $from;
		$this->subject = $subject;
		$this->date = Carbon::parse($date);
		$this->client = &$client;
	}

	public function __get(string $name): string {
		if (strcmp($name, 'textHtml') !== 0) {
			throw new \InvalidArgumentException($name . ' is not a valid attribute');
		}

		if (!empty($this->textHtml)) {
			return $this->textHtml;
		}

		$this->textHtml = $this->client->getMessage($this->id)->body ?? '';
		return $this->textHtml;
	}

	public function __isset($name): bool {
		if (strcmp($name, 'textHtml') === 0) {
			return true;
		}

		return isset($this->$name);
	}
}
