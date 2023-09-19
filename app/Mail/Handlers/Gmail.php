<?php

namespace App\Mail\Handlers;

use App\Mail\Contracts\Mail;

class Gmail extends Mail {
	protected string $hostName = 'imap.gmail.com';
	protected string $port = '993';

	public function getConnectionString(): string {
		return '{' . $this->hostName . ':' . $this->port . '/imap/ssl}INBOX';
	}
}
