<?php
/**
 * @copyright Copyright (c) 2019 Jonas Sulzer <jonas@violoncello.ch>
 *
 * @author Jonas Sulzer <jonas@violoncello.ch>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\user_external\Settings;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Defaults;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Settings\ISettings;
class Admin implements ISettings {
	/** @var IL10N */
	private $l10n;
	/** @var Defaults */
	private $defaults;
	/** @var IConfig */
	private $config;
	/**
	 * @param IL10N $l10n
	 * @param Defaults $defaults
	 * @param IConfig $config
	 */
	public function __construct(IL10N $l10n,
								Defaults $defaults,
								IConfig $config) {
		$this->l10n = $l10n;
		$this->defaults = $defaults;
		$this->config = $config;
	}
	/**
	 * @return TemplateResponse
	 */
	public function getForm() {

    $serverData = [
			'user_backends' => $this->config->getSystemValue(user_backends)
    ];

		return new TemplateResponse('user_external', 'admin', ['serverData' => $serverData]);
	}
	/**
	 * @return string the section ID, e.g. 'sharing'
	 */
	public function getSection() {
		return 'user_external';
	}
	/**
	 * @return int whether the form should be rather on the top or bottom of
	 * the admin section. The forms are arranged in ascending order of the
	 * priority values. It is required to return a value between 0 and 100.
	 *
	 * keep the server setting at the top, right after "server settings"
	 */
	public function getPriority() {
		return 0;
	}
}
