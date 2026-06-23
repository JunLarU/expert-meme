<?php

namespace App\Middlewares;

use Closure;
use Whis\Auth\Api\ApiTokenGuard;
use Whis\Auth\Auth;
use Whis\Http\Middleware;
use Whis\Http\Request;
use Whis\Http\Response;

class ApiTokenMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(ApiTokenGuard::class)->authenticate($request);

        if (!$context) {
            return Response::json([
                'ok'      => false,
                'message' => 'Token de API ausente, inválido, expirado o revocado.',
            ])
                ->setStatus(401)
                ->setHeader('WWW-Authenticate', 'Bearer');
        }

        Auth::setApiContext($context);

        try {
            return $next($request);
        } finally {
            Auth::forgetApiContext();
        }
    }
}
