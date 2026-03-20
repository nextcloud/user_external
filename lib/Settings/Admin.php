<?php

declare(strict_types=1);

namespace OCA\UserExternal\Settings;

use OCA\UserExternal\Service\BackendConfigService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;

class Admin implements ISettings {
	public function __construct(
		private readonly BackendConfigService $backendConfigService,
		private readonly IInitialState $initialState,
	) {
	}

	#[\Override]
	public function getForm(): TemplateResponse {
		$this->initialState->provideInitialState('backends', $this->backendConfigService->getBackends());

		return new TemplateResponse('user_external', 'admin', [], TemplateResponse::RENDER_AS_BLANK);
	}

	#[\Override]
	public function getSection(): string {
		return 'user_external';
	}

	#[\Override]
	public function getPriority(): int {
		return 50;
	}
}
