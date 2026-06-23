<?php

namespace App\Controllers\Api;

use Whis\Auth\Api\ApiTokenGuard;
use Whis\Auth\Auth;
use Whis\Http\Request;
use Whis\Http\Response;

class TokenController
{
    public function index(): Response
    {
        return Response::json([
            'ok'     => true,
            'tokens' => app(ApiTokenGuard::class)->tokensFor(Auth::user()),
        ]);
    }

    public function store(Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return Response::json([
                'ok'      => false,
                'message' => 'Debes iniciar sesión para crear tokens.',
            ])->setStatus(401);
        }

        $name = (string) ($request->data('name') ?: 'API Token');
        $abilities = $this->normalizeAbilities($request->data('abilities') ?: ['*']);
        $expiresAt = $this->expiresAt($request->data('expires_at'));

        $issued = app(ApiTokenGuard::class)->issue(
            user: $user,
            name: $name,
            abilities: $abilities,
            expiresAt: $expiresAt
        );

        return Response::json([
            'ok'             => true,
            'message'        => 'Token creado correctamente. Guárdalo ahora; no se volverá a mostrar.',
            'plainTextToken' => $issued['plainTextToken'],
            'token'          => $this->publicToken($issued['token']),
        ])->setStatus(201);
    }

    public function destroy(int|string $id): Response
    {
        app(ApiTokenGuard::class)->revokeById($id);

        return Response::json([
            'ok'      => true,
            'message' => 'Token revocado correctamente.',
        ]);
    }

    private function normalizeAbilities(mixed $abilities): array
    {
        if (is_string($abilities)) {
            $decoded = json_decode($abilities, true);

            if (is_array($decoded)) {
                $abilities = $decoded;
            } else {
                $abilities = explode(',', $abilities);
            }
        }

        if (!is_array($abilities)) {
            return ['*'];
        }

        $abilities = array_values(array_filter(
            array_map(fn($ability) => trim((string) $ability), $abilities),
            fn($ability) => $ability !== ''
        ));

        return $abilities ?: ['*'];
    }

    private function expiresAt(mixed $value): ?\DateTimeInterface
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function publicToken(?array $token): ?array
    {
        if (!$token) {
            return null;
        }

        unset($token['token_hash']);

        return $token;
    }
}
