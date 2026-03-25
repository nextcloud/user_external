<?php

declare(strict_types=1);

return [
	'routes' => [
		['name' => 'Config#getBackends', 'url' => '/api/v1/backends', 'verb' => 'GET'],
		['name' => 'Config#setBackends', 'url' => '/api/v1/backends', 'verb' => 'PUT'],
	],
];
