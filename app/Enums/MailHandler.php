<?php

namespace App\Enums;

enum MailHandler {
	case GMAIL;
	case OUTLOOK;
	case YAHOO;
	case SECMAIL;

	public function getClassName(): string {
		return ucwords(strtolower($this->name));
	}
}
