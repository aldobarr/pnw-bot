<?php

namespace App\Mail\Handlers;

use App\Mail\Contracts\Mail;

class Outlook extends Mail {
	protected string $hostName = 'outlook.office365.com';
	protected string $port = '993';

	public function getConnectionString(): string {
		return '{' . $this->hostName . ':' . $this->port . '/imap/ssl}INBOX';
	}
}
