<?php
declare (strict_types = 1);
/**
 * @copyright Copyright (c) 2019 Jonas Sulzer (violoncelloCH) <jonas@violoncello.ch>
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
namespace OCA\user_external\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Controller;
use OCP\IConfig;
use OCP\IRequest;
class ConfigController extends Controller {
	/** @var string */
	protected $appName;
	/** @var string */
	protected $userId;
	/** @var string */
	protected $serverRoot;
	/** @var IConfig */
	private $config;
	/**
	 * Config constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IConfig $config
	 */
	public function __construct(string $appName,
								IRequest $request,
								IConfig $config) {
		parent::__construct($appName, $request);
		$this->appName               = $appName;
		$this->config                = $config;
	}
	/**
	 * Get user_external config
	 *
	 * @return JSONResponse
	 */
	public function getConfig(): JSONResponse {
		return new JSONResponse([
			'user_backends' => $this->config->getSystemValue(user_backends)
		]);
	}
	/**
	 * Set user_external config
	 *
	 * @param array $config
	 * @return JSONResponse
	 * @throws Exception
	 */
	public function setConfig(array $config): JSONResponse {
		$this->config->setSystemValue('user_backends', $config);

		return new JSONResponse([
			null
		]);

  }
}
