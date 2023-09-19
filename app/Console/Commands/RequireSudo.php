<?php

namespace App\Console\Commands;

trait RequireSudo {
	public function requireSudo() {
		$process_user = posix_getpwuid(posix_geteuid());
		if (empty($process_user) || empty($process_user['name']) || strcmp($process_user['name'], 'root') !== 0) {
			throw new \Symfony\Component\Console\Exception\RuntimeException('Invalid user exception: Must run as root');
		}
	}
}