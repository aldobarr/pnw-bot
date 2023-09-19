<?php

namespace App\Mail\Contracts;

use App\Mail\Handlers\SecmailMessage;
use App\Services\Browser\LoginService;
use PhpImap\IncomingMail;
use PhpImap\Mailbox;

abstract class Mail {
	protected Mailbox $client;
	protected string $login;
	protected string $password;
	protected string $hostName;
	protected string $port;

	public function __construct(string $login, string $password) {
		$this->login = $login;
		$this->password = $password;
		$this->client = new Mailbox($this->getConnectionString(), $this->login, $this->password);
		$this->client->setConnectionRetry(3);
		$this->client->setAttachmentFilenameMode(true);
		$this->client->setAttachmentsIgnore(true);
		$this->client->setTimeouts(30, [IMAP_OPENTIMEOUT, IMAP_CLOSETIMEOUT, IMAP_READTIMEOUT]);
	}

	public function getAllInboxes(): array {
		return $this->client->getMailboxes();
	}

	public function setMailbox(string $fullpath): void {
		$this->client->switchMailbox($fullpath);
	}

	public function getMailboxMessages(string $criteria = 'ALL'): array {
		return $this->client->searchMailbox($criteria, true);
	}

	public function getPNWMessages(): array {
		return $this->client->searchMailbox('FROM "@' . LoginService::HOST_NAME . '"', true);
	}

	public function getMail(int|SecmailMessage $id): IncomingMail|SecmailMessage {
		if (($id instanceof SecmailMessage)) {
			return $id;
		}

		return $this->client->getMail($id, false);
	}

	public function markRead(int $id): void {
		$this->client->markMailAsRead($id);
	}

	abstract public function getConnectionString(): string;
}
