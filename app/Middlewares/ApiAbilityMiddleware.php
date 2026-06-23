<?php

namespace App\Middlewares;

use Closure;
use Whis\Auth\Auth;
use Whis\Http\Middleware;
use Whis\Http\Request;
use Whis\Http\Response;

class ApiAbilityMiddleware implements Middleware
{
    /**
     * @var array<int,string>
     */
    protected array $abilities = [];

    public function __construct(array|string $abilities = [])
    {
        $this->abilities = is_string($abilities) ? [$abilities] : $abilities;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::apiToken()) {
            return Response::json([
                'ok'      => false,
                'message' => 'Esta ruta requiere autenticación por token.',
            ])->setStatus(401);
        }

        foreach ($this->abilities as $ability) {
            if (!Auth::tokenCan($ability)) {
                return Response::json([
                    'ok'      => false,
                    'message' => 'El token no tiene permisos suficientes.',
                    'ability' => $ability,
                ])->setStatus(403);
            }
        }

        return $next($request);
    }
}
