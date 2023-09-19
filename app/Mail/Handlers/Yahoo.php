<?php

namespace App\Mail\Handlers;

use App\Mail\Contracts\Mail;

class Yahoo extends Mail {
	protected string $hostName = 'imap.mail.yahoo.com';
	protected string $port = '993';

	public function getConnectionString(): string {
		return '{' . $this->hostName . ':' . $this->port . '/imap/ssl}INBOX';
	}
}
