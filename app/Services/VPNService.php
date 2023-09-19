<?php

namespace App\Services;

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class VPNService {
	private $servers;
	private const MAX_ATTEMPTS = 5;
	private const CONNECT_ATTEMPTS = 10;

	private const BLOCKED_COUNTRIES = [
		'za' => true,
	];

	private const BLOCKED_REGIONS = [
		'us' => [
			'qas' => true,
		],
	];

	private const BLOCKED_HOSTNAMES = [
		'us-atl-ovpn-001' => true,
		'us-atl-ovpn-002' => true,
	];

	public function __construct() {
		$this->updateRelay();
	}

	public function connect(string $server): void {
		$success = false;
		$attempts = static::MAX_ATTEMPTS;
		$process = Process::fromShellCommandline('mullvad relay set hostname ' . $server);

		do {
			if ($process->run() === 0) {
				$success = true;
				break;
			}
		} while ($attempts-- > 0);

		if (!$success) {
			$process->mustRun();
		}

		$success = false;
		$attempts = static::MAX_ATTEMPTS;
		$process = Process::fromShellCommandline('mullvad connect');

		do {
			if ($process->run() === 0) {
				$success = true;
				break;
			}
		} while ($attempts-- > 0);

		if (!$success) {
			$process->mustRun();
		}

		$attempts = 0;

		do {
			sleep(1);
		} while (!$this->isConnected() && $attempts++ < static::CONNECT_ATTEMPTS);
	}

	public function findServer(array $disallowed_map = []): ?string {
		$servers = $this->getServers();
		$prio_countries = ['us', 'ca', 'gb', 'es', 'de', 'fr'];
		foreach ($prio_countries as $country) {
			$hostnames = $servers[$country] ?? [];
			foreach ($hostnames as $hostname => $ips) {
				if (!array_key_exists($hostname, $disallowed_map)) {
					return $hostname;
				}
			}

			unset($servers[$country]);
		}

		foreach ($servers as $country => $hostnames) {
			foreach ($hostnames as $hostname => $ips) {
				if (!array_key_exists($hostname, $disallowed_map)) {
					return $hostname;
				}
			}
		}

		return null;
	}

	public function excludeCurrentProcess(): void {
		$success = false;
		$attempts = static::MAX_ATTEMPTS;
		$process = Process::fromShellCommandline('mullvad split-tunnel pid add ' . getmypid());

		do {
			if ($process->run() === 0) {
				$success = true;
				break;
			}
		} while ($attempts-- > 0);

		if (!$success) {
			$process->mustRun();
		}
	}

	public function restoreCurrentProcess(): void {
		$success = false;
		$attempts = static::MAX_ATTEMPTS;
		$process = Process::fromShellCommandline('mullvad split-tunnel pid delete ' . getmypid());

		do {
			if ($process->run() === 0) {
				$success = true;
				break;
			}
		} while ($attempts-- > 0);

		if (!$success) {
			$process->mustRun();
		}
	}

	public function getServers(): array {
		if (!empty($this->servers)) {
			return $this->servers;
		}

		$process = Process::fromShellCommandline('mullvad relay list');
		$process->mustRun();

		$this->servers = [];
		$lines = explode(PHP_EOL, $process->getOutput());
		foreach ($lines as $line) {
			$line = trim($line);
			$line_check = strtolower($line);
			if (empty($line) || (!Str::contains($line_check, '-wg-') && !Str::contains($line_check, '-ovpn-'))) {
				continue;
			}

			[$hostname, $ipv4, $ipv6] = explode(' ', $line);
			[$country, $region] = explode('-', $hostname);
			if (
				array_key_exists($hostname, static::BLOCKED_HOSTNAMES) ||
				array_key_exists($country, static::BLOCKED_COUNTRIES) ||
				(
					array_key_exists($country, static::BLOCKED_REGIONS) &&
					array_key_exists($region, static::BLOCKED_REGIONS[$country])
				)
			) {
				continue;
			}

			if (!array_key_exists($country, $this->servers)) {
				$this->servers[$country] = [];
			}

			if (!empty($ipv6) && (!Str::contains($ipv6, '::') || !Str::endsWith($ipv6, ')'))) {
				$ipv6 = null;
			} else {
				$ipv6 = str_ireplace(['(', ')', ','], '', trim($ipv6));
			}

			$this->servers[$country][$hostname] = ['ipv4' => str_ireplace(['(', ')', ','], '', trim($ipv4)), 'ipv6' => $ipv6];
		}

		return $this->servers;
	}

	public function disconnect(): void {
		$success = false;
		$attempts = static::MAX_ATTEMPTS;
		$process = Process::fromShellCommandline('mullvad disconnect');

		do {
			if ($process->run() === 0) {
				$success = true;
				break;
			}
		} while ($attempts-- > 0);

		if (!$success) {
			$process->mustRun();
		}

		$attempts = 0;

		do {
			sleep(1);
		} while ($this->isConnected() && $attempts++ < static::CONNECT_ATTEMPTS);
	}

	public function isConnected(?string $enforce_server = null): bool {
		$success = false;
		$attempts = static::MAX_ATTEMPTS;
		$process = Process::fromShellCommandline('mullvad status');

		do {
			if ($process->run() === 0) {
				$success = true;
				break;
			}
		} while ($attempts-- > 0);

		if (!$success) {
			$process->mustRun();
		}

		$status = trim($process->getOutput());
		if ($enforce_server !== null && !Str::contains($status, $enforce_server)) {
			return false;
		}

		return strcasecmp($status, 'disconnected') !== 0 && Str::startsWith(strtolower($status), 'connected');
	}

	public function updateRelay(): void {
		$process = Process::fromShellCommandline('mullvad relay update');
		$process->mustRun();
	}
}
