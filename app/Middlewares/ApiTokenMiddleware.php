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
        $guard = app(ApiTokenGuard::class);
        $context = $guard->authenticate($request);

        if (! $context) {
            return Response::json([
                'ok'      => false,
                'message' => 'Token de API ausente, inválido, expirado o revocado.',
            ])
                ->setStatus(401)
                ->setHeader('WWW-Authenticate', 'Bearer');
        }

        Auth::setApiContext($context);

        // Opcional: rate limiting por token (ejemplo con Redis o almacenamiento en sesión)
        // $this->applyRateLimit($context->token);

        try {
            return $next($request);
        } finally {
            Auth::forgetApiContext();
        }
    }

    private function applyRateLimit(array $token): void
    {
        // Ejemplo simple: limitar a 100 peticiones por minuto por token
        // Implementación con un almacén de caché (Redis, o incluso sesión)
        // Aquí puedes usar un servicio de rate limit propio.
    }
}