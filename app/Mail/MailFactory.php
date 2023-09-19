<?php

namespace App\Mail;

use App\Enums\MailHandler;
use App\Mail\Contracts\Mail;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;

class MailFactory {
	public static function create(MailHandler $type, string $login, string $password): Mail {
		$handler = 'App\\Mail\\Handlers\\' . $type->getClassName();
		if (!class_exists($handler)) {
			throw new InvalidArgumentException($type->name);
		}

		return new $handler($login, $password);
	}
}
