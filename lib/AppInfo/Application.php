<?php

declare(strict_types=1);

namespace OCA\UserExternal\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Notification\IManager;
use OCP\User\Events;

class Application extends App implements IBootstrap {

    public function __construct() {
        parent::__construct('user_external');
    }

    public function register(IRegistrationContext $context): void {
    }

    public function boot(IBootContext $context): void {
    }

}