<?php

namespace App\Providers;

use Whis\Auth\Api\ApiTokenGuard;

class ApiAuthServiceProvider
{
    public function registerServices(): void
    {
        singleton(ApiTokenGuard::class, fn () => new ApiTokenGuard());
    }
}