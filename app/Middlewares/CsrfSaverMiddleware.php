<?php

namespace App\Middlewares;

use Whis\Support\Csrf;
use Closure;
use Whis\Http\Middleware;
use Whis\Http\Request;
use Whis\Http\Response;

class CsrfSaverMiddleware implements Middleware
{
    private const TOKEN_FIELD = '_token';
    private const KEY_FIELD = '_csrf_key';

    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->getCsrfKey($request);
        $token = $this->getCsrfToken($request);

        /*
         * IMPORTANTE:
         * No consumas el token aquí.
         * Si lo consumes antes y luego falla una validación, el frontend queda
         * con un token muerto y el siguiente submit puede terminar en redirect.
         */
        $valid = Csrf::validate($key, $token, false);

        if (!$valid) {
            $valid = $this->validateLegacyToken($key, $token);
        }

        if (!$valid) {
            return $this->invalidCsrfResponse($request, $key);
        }

        $response = $next($request);

        if ($request->expectsJson()) {
            $response = $this->attachFreshToken($response, $key);
        }

        return $response;
    }

    private function getCsrfKey(Request $request): string
    {
        $key =
            $request->data(self::KEY_FIELD)
            ?: $request->headers('x-csrf-key')
            ?: 'default';

        return trim((string) $key) ?: 'default';
    }

    private function getCsrfToken(Request $request): string
    {
        $token =
            $request->data(self::TOKEN_FIELD)
            ?: $request->headers('x-csrf-token')
            ?: '';

        return trim((string) $token);
    }

    private function validateLegacyToken(string $key, string $token): bool
    {
        if ($key !== 'default') {
            return false;
        }

        $legacyToken = session()->get('_token');

        if (!$legacyToken || !is_string($legacyToken)) {
            return false;
        }

        return hash_equals($legacyToken, $token);
    }

    private function invalidCsrfResponse(Request $request, string $key): Response
    {
        $freshToken = Csrf::generate($key);

        if ($request->expectsJson()) {
            return Response::json([
                'ok'        => false,
                'error'     => 'La sesión del formulario expiró. Intenta enviarlo nuevamente.',
                'message'   => 'La sesión del formulario expiró. Intenta enviarlo nuevamente.',
                'csrfKey'   => $key,
                'csrfToken' => $freshToken,
            ])->setStatus(419);
        }

        /*
         * Solo formularios normales deben redirigir.
         * Las peticiones AJAX jamás deberían caer aquí.
         */
        return Response::redirect('/');
    }

    private function attachFreshToken(Response $response, string $key): Response
    {
        $freshToken = Csrf::generate($key);

        $response->setHeader('X-CSRF-Key', $key);
        $response->setHeader('X-CSRF-Token', $freshToken);

        $contentType = strtolower((string) $response->headers('content-type'));
        $content = $response->content();

        if ($content && str_contains($contentType, 'application/json')) {
            $data = json_decode($content, true);

            if (is_array($data)) {
                $data['csrfKey'] = $data['csrfKey'] ?? $key;
                $data['csrfToken'] = $data['csrfToken'] ?? $freshToken;

                $response
                    ->setContentType('application/json')
                    ->setContent(json_encode($data, JSON_UNESCAPED_UNICODE));
            }
        }

        return $response;
    }
}