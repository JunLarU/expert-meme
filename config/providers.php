<?php

return [
    'boot' => [
        Whis\Providers\ServerServiceProvider::class,
        Whis\Providers\DatabaseDriverServiceProvider::class,
        Whis\Providers\SessionStorageServiceProvider::class,
        Whis\Providers\ViewServiceProvider::class,
        Whis\Providers\AuthenticationServiceProvider::class,
        Whis\Providers\HasherServiceProvider::class,
        Whis\Providers\FileStorageDriverServiceProvider::class,
    ],

    'runtime' => [
        App\Providers\RuleServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        App\Providers\AppServiceProvider::class,
    ],
    'cli'=>[
        Whis\Providers\DatabaseDriverServiceProvider::class,
    ]
];