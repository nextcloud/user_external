<?php

declare(strict_types=1);

namespace OCA\UserExternal\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection {
	public function __construct(
		private readonly IURLGenerator $url,
		private readonly IL10N $l,
	) {
	}

	#[\Override]
	public function getIcon(): string {
		return $this->url->imagePath('user_external', 'app.svg');
	}

	#[\Override]
	public function getID(): string {
		return 'user_external';
	}

	#[\Override]
	public function getName(): string {
		return $this->l->t('External user authentication');
	}

	#[\Override]
	public function getPriority(): int {
		return 75;
	}
}
