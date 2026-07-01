<?php

declare(strict_types=1);

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'settings#get', 'url' => '/api/settings', 'verb' => 'GET'],
        ['name' => 'settings#save', 'url' => '/api/settings', 'verb' => 'PUT'],
        ['name' => 'settings#syncNow', 'url' => '/api/sync', 'verb' => 'POST'],
    ],
];
