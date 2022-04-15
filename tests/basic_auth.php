<?php
/**
 * Copyright (c) 2019 Lutz Freitag <lutz.freitag@gottliebtfreitag.de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

class Test_User_BasicAuth extends \Test\TestCase {
	/**
	 * @var OC_User_BasicAuth $instance
	 */
	private $instance;

	private function getConfig() {
		return include(__DIR__.'/config.php');
	}

	public function skip() {
		$config = $this->getConfig();
		$this->skipUnless($config['basic_auth']['run']);
	}

	protected function setUp() {
		parent::setUp();
		$config = $this->getConfig();
		$this->instance = new OC_User_BasicAuth($config['basic_auth']['url']);
	}

	public function testLogin() {
		$config = $this->getConfig();
		$this->assertEquals($config['basic_auth']['user'], $this->instance->checkPassword($config['basic_auth']['user'], $config['basic_auth']['password']));
		$this->assertFalse($this->instance->checkPassword($config['basic_auth']['user'], $config['basic_auth']['password'].'foo'));
	}
}
