<?php

namespace Whis\Auth\Api;

use Whis\Auth\Authenticatable;

class ApiTokenResult
{
    public function __construct(
        public array $token,
        public ?Authenticatable $user = null
    ) {
    }
}
