<?php
/**
 * Copyright (c) 2021 Sebastian Sterk <sebastian@wiuwiu.de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

class Test_User_HTTP extends \Test\TestCase {
	/**
	 * @var OC_User_HTTP $instance
	 */
	private $instance;

	private function getConfig() {
		return include(__DIR__.'/config.php');
	}

	public function skip() {
		$config = $this->getConfig();
		$this->skipUnless($config['http']['run']);
	}

	protected function setUp() {
		parent::setUp();

		$config = $this->getConfig();
		$this->instance = new OC_User_HTTP(
			$config['http']['endpoint'],
			$config['http']['hashAlgo'],
			$config['http']['accessKey']
		);
	}

	public function testLogin() {
		$config = $this->getConfig();
		$this->assertEquals($config['http']['user'], $this->instance->checkPassword($config['http']['user'], $config['http']['password']));
		$this->assertFalse($this->instance->checkPassword($config['http']['user'], $config['http']['password'].'foo'));
	}
}
