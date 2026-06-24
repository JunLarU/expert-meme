<?php

namespace App\Middlewares;

use Closure;
use Whis\Http\Middleware;
use Whis\Http\Request;
use Whis\Http\Response;
use Whis\Support\Csrf;

class SiteApiMiddleware implements Middleware
{
    private array $safeMethods = [
        'GET',
        'HEAD',
        'OPTIONS',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        if ($method === 'OPTIONS') {
            return Response::json([
                'ok' => true,
            ]);
        }

        if (! in_array($method, $this->safeMethods, true)) {
            if (! $this->isSameOrigin($request)) {
                return $this->deny('Origen no permitido.', 403);
            }

            if (! $this->validCsrf($request)) {
                return $this->deny('CSRF inválido o expirado.', 419);
            }
        }

        $response = $next($request);

        return $response;
    }

    private function validCsrf(Request $request): bool
    {
        $key = $this->input($request, '_csrf_key')
            ?: $this->header($request, 'x-csrf-key')
            ?: 'site-api';

        $token = $this->input($request, '_token')
            ?: $this->header($request, 'x-csrf-token');

        return Csrf::validate($key, $token, true);
    }

    private function isSameOrigin(Request $request): bool
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));

        if ($host === '') {
            return false;
        }

        $origin = $this->header($request, 'origin');

        if ($origin !== '') {
            return $this->hostFromUrl($origin) === $host;
        }

        $referer = $this->header($request, 'referer');

        if ($referer !== '') {
            return $this->hostFromUrl($referer) === $host;
        }

        /*
         * Algunos fetch same-origin no mandan Origin en GET,
         * pero para mutaciones sí suele venir Origin/Referer.
         * Para máxima seguridad, si no hay ninguno, rechazamos.
         */
        return false;
    }

    private function hostFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);

        if (! is_string($host) || $host === '') {
            return '';
        }

        $host = strtolower($host);

        if ($port) {
            $host .= ':' . $port;
        }

        return $host;
    }

    private function input(Request $request, string $key): string
    {
        $value = $request->data($key);

        return trim((string) ($value ?? ''));
    }

    private function header(Request $request, string $key): string
    {
        $value = $request->headers($key);

        return trim((string) ($value ?? ''));
    }

    private function deny(string $message, int $status): Response
    {
        return Response::json([
            'ok'      => false,
            'message' => $message,
        ])->setStatus($status);
    }
}