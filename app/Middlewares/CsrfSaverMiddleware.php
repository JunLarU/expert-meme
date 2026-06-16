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

        $valid = Csrf::validate($key, $token, true);

        /*
         * Compatibilidad con la versión vieja:
         * session()->set('_token', $token)
         */
        if (!$valid) {
            $valid = $this->validateLegacyToken($key, $token);
        }

        if (!$valid) {
            return $this->invalidCsrfResponse($request, $key);
        }

        $response = $next($request);

        /*
         * Para ajax-form, el token se consume y se entrega uno nuevo.
         * Para formularios normales no hace falta porque normalmente hay redirect/recarga.
         */
        if ($this->expectsJson($request)) {
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

        if (!hash_equals($legacyToken, $token)) {
            return false;
        }

        session()->remove('_token');

        return true;
    }

    private function invalidCsrfResponse(Request $request, string $key): Response
    {
        if (!$this->expectsJson($request)) {
            return Response::redirect('/');
        }

        $freshToken = Csrf::generate($key);

        return Response::json([
            'ok' => false,
            'error' => 'La sesión del formulario expiró. Intenta enviarlo nuevamente.',
            'csrfKey' => $key,
            'csrfToken' => $freshToken,
        ])->setStatus(419);
    }

    private function attachFreshToken(Response $response, string $key): Response
    {
        $freshToken = Csrf::generate($key);

        $response->setHeader('X-CSRF-Key', $key);
        $response->setHeader('X-CSRF-Token', $freshToken);

        $contentType = strtolower((string) $response->headers('content-type'));
        $content = $response->content();

        if (
            $content &&
            str_contains($contentType, 'application/json')
        ) {
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

    private function expectsJson(Request $request): bool
    {
        $accept = strtolower((string) $request->headers('accept'));
        $requestedWith = strtolower((string) $request->headers('x-requested-with'));

        return str_contains($accept, 'application/json')
            || $requestedWith === 'xmlhttprequest';
    }
}