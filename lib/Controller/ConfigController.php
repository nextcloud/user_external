<?php

declare(strict_types=1);

namespace OCA\UserExternal\Controller;

use OCA\UserExternal\Service\BackendConfigService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class ConfigController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly BackendConfigService $backendConfigService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Returns the current user_backends configuration.
	 * Requires admin (no @NoAdminRequired).
	 */
	#[NoCSRFRequired]
	public function getBackends(): JSONResponse {
		return new JSONResponse($this->backendConfigService->getBackends());
	}

	/**
	 * Replaces the user_backends configuration.
	 * Requires admin (no @NoAdminRequired).
	 *
	 * @param list<array{class: string, arguments: list<mixed>}> $backends
	 */
	public function setBackends(array $backends): JSONResponse {
		// Validate that each entry has the required keys and an allowed class.
		$allowed = [
			'\\OCA\\UserExternal\\IMAP',
			'\\OCA\\UserExternal\\FTP',
			'\\OCA\\UserExternal\\SMB',
			'\\OCA\\UserExternal\\SSH',
			'\\OCA\\UserExternal\\BasicAuth',
			'\\OCA\\UserExternal\\WebDavAuth',
			'\\OCA\\UserExternal\\XMPP',
		];

		foreach ($backends as $backend) {
			if (!isset($backend['class'], $backend['arguments'])) {
				return new JSONResponse(['error' => 'Invalid backend format'], Http::STATUS_BAD_REQUEST);
			}
			if (!in_array($backend['class'], $allowed, true)) {
				return new JSONResponse(['error' => 'Unknown backend class: ' . $backend['class']], Http::STATUS_BAD_REQUEST);
			}
		}

		$this->backendConfigService->setBackends($backends);
		return new JSONResponse(['status' => 'ok']);
	}
}
