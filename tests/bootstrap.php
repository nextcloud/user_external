<?php

declare(strict_types=1);

if (!defined('PHPUNIT_RUN')) {
	define('PHPUNIT_RUN', 1);
}

require_once __DIR__ . '/../../../lib/base.php';
require_once __DIR__ . '/../../../tests/autoload.php';

\OC::$composerAutoloader->addPsr4('OCA\\UserExternal\\', __DIR__ . '/../lib/', true);
\OC::$composerAutoloader->addPsr4('OCA\\UserExternal\\Tests\\', __DIR__ . '/', true);
