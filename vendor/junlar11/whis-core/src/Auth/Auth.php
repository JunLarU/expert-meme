<?php

namespace Whis\Auth;

use App\Controllers\Auth\LoginController;
use App\Controllers\Auth\RegisterController;
use App\Middlewares\CsrfSaverMiddleware;
use Whis\Auth\Api\ApiTokenGuard;
use Whis\Auth\Api\ApiTokenResult;
use Whis\Auth\Authenticators\Authenticator;
use Whis\Routing\Route;

class Auth
{
    protected static ?ApiTokenResult $apiContext = null;

    public static function user(): ?Authenticatable
    {
        return self::apiUser() ?? app(Authenticator::class)->resolve();
    }

    public static function sessionUser(): ?Authenticatable
    {
        return app(Authenticator::class)->resolve();
    }

    public static function apiUser(): ?Authenticatable
    {
        return self::$apiContext?->user;
    }

    public static function apiToken(): ?array
    {
        return self::$apiContext?->token;
    }

    public static function setApiContext(ApiTokenResult $context): void
    {
        self::$apiContext = $context;
    }

    public static function forgetApiContext(): void
    {
        self::$apiContext = null;
    }

    public static function tokenCan(string $ability): bool
    {
        $token = self::apiToken();

        if (!$token) {
            return false;
        }

        return app(ApiTokenGuard::class)->tokenCan($token, $ability);
    }

    public static function isGuest(): bool
    {
        return is_null(self::user());
    }

    public static function Routes(): void
    {
        Route::get('/register', [RegisterController::class, 'create']);
        Route::get('/login', [LoginController::class, 'create']);
        Route::post('/login', [LoginController::class, 'store'])->setMiddlewares([CsrfSaverMiddleware::class]);
        Route::post('/register', [RegisterController::class, 'store'])->setMiddlewares([CsrfSaverMiddleware::class]);
        Route::get('/logout', [LoginController::class, 'destroy']);
    }
}
