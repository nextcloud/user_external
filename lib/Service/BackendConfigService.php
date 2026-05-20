<?php

declare(strict_types=1);

namespace OCA\UserExternal\Service;

use OCP\IConfig;

/**
 * Reads and writes the user_backends system config key.
 */
class BackendConfigService {
	public function __construct(
		private readonly IConfig $config,
	) {
	}

	/** @return list<array{class: string, arguments: list<mixed>}> */
	public function getBackends(): array {
		return $this->config->getSystemValue('user_backends', []);
	}

	/** @param list<array{class: string, arguments: list<mixed>}> $backends */
	public function setBackends(array $backends): void {
		$this->config->setSystemValue('user_backends', $backends);
	}
}
