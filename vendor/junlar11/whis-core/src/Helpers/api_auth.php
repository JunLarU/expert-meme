<?php

use Whis\Auth\Auth;
use Whis\Auth\Authenticatable;

if (!function_exists('api_user')) {
    function api_user(): ?Authenticatable
    {
        return Auth::apiUser();
    }
}

if (!function_exists('api_token')) {
    function api_token(): ?array
    {
        return Auth::apiToken();
    }
}

if (!function_exists('api_token_can')) {
    function api_token_can(string $ability): bool
    {
        return Auth::tokenCan($ability);
    }
}
