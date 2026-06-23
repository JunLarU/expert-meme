<?php

namespace Whis\Auth\Api;

use App\Models\ApiToken;
use Whis\Auth\Authenticatable;
use Whis\Database\Model;
use Whis\Http\Request;

class ApiTokenGuard
{
    private const PREFIX = 'whis_';

    public function issue(
        ?Authenticatable $user,
        string $name = 'API Token',
        array $abilities = ['*'],
        ?\DateTimeInterface $expiresAt = null
    ): array {
        $plainToken = $this->makePlainToken();
        $now = $this->now();

        $record = [
            'tokenable_type' => $user ? $user::class : null,
            'tokenable_id'   => $user?->id(),
            'name'           => trim($name) !== '' ? trim($name) : 'API Token',
            'token_prefix'   => substr($plainToken, 0, 18),
            'token_hash'     => $this->hash($plainToken),
            'abilities'      => json_encode(array_values($abilities ?: ['*']), JSON_UNESCAPED_UNICODE),
            'expires_at'     => $expiresAt?->format('Y-m-d H:i:s'),
            'last_used_at'   => null,
            'revoked_at'     => null,
            'created_at'     => $now,
            'updated_at'     => null,
        ];

        Model::getDatabaseDriver()->statement(
            'INSERT INTO api_tokens
                (tokenable_type, tokenable_id, name, token_prefix, token_hash, abilities, expires_at, last_used_at, revoked_at, created_at, updated_at)
             VALUES
                (:tokenable_type, :tokenable_id, :name, :token_prefix, :token_hash, :abilities, :expires_at, :last_used_at, :revoked_at, :created_at, :updated_at)',
            $record
        );

        return [
            'plainTextToken' => $plainToken,
            'token'          => $this->findByHash($this->hash($plainToken)),
        ];
    }

    public function authenticate(Request $request): ?ApiTokenResult
    {
        $plainToken = $this->bearerToken($request);

        if ($plainToken === null) {
            return null;
        }

        $token = $this->findByHash($this->hash($plainToken));

        if (!$token || !$this->isUsable($token)) {
            return null;
        }

        $this->markAsUsed((int) $token['id']);

        return new ApiTokenResult(
            token: $token,
            user: $this->resolveTokenUser($token)
        );
    }

    public function revokeByPlainToken(string $plainToken): bool
    {
        $token = $this->findByHash($this->hash($plainToken));

        if (!$token) {
            return false;
        }

        return $this->revokeById((int) $token['id']);
    }

    public function revokeById(int|string $id): bool
    {
        Model::getDatabaseDriver()->statement(
            'UPDATE api_tokens SET revoked_at = :revoked_at, updated_at = :updated_at WHERE id = :id AND revoked_at IS NULL',
            [
                ':id'         => $id,
                ':revoked_at' => $this->now(),
                ':updated_at' => $this->now(),
            ]
        );

        return true;
    }

    public function revokeUserTokens(Authenticatable $user): void
    {
        Model::getDatabaseDriver()->statement(
            'UPDATE api_tokens
             SET revoked_at = :revoked_at, updated_at = :updated_at
             WHERE tokenable_type = :type AND tokenable_id = :id AND revoked_at IS NULL',
            [
                ':type'       => $user::class,
                ':id'         => $user->id(),
                ':revoked_at' => $this->now(),
                ':updated_at' => $this->now(),
            ]
        );
    }

    public function tokensFor(?Authenticatable $user): array
    {
        if (!$user) {
            return [];
        }

        return Model::getDatabaseDriver()->statement(
            'SELECT id, tokenable_type, tokenable_id, name, token_prefix, abilities, expires_at, last_used_at, revoked_at, created_at, updated_at
             FROM api_tokens
             WHERE tokenable_type = :type AND tokenable_id = :id
             ORDER BY id DESC',
            [
                ':type' => $user::class,
                ':id'   => $user->id(),
            ]
        );
    }

    public function tokenCan(array $token, string $ability): bool
    {
        $abilities = $this->abilities($token);

        return in_array('*', $abilities, true)
            || in_array($ability, $abilities, true);
    }

    public function abilities(array $token): array
    {
        $abilities = json_decode((string) ($token['abilities'] ?? '[]'), true);

        if (!is_array($abilities)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn($ability) => trim((string) $ability), $abilities),
            fn($ability) => $ability !== ''
        ));
    }

    public function bearerToken(Request $request): ?string
    {
        $authorization = $request->headers('authorization')
            ?: $request->headers('Authorization')
            ?: ($_SERVER['HTTP_AUTHORIZATION'] ?? null)
            ?: ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null);

        if (!$authorization && function_exists('getallheaders')) {
            $headers = getallheaders();
            $authorization = $headers['Authorization']
                ?? $headers['authorization']
                ?? null;
        }

        if (!is_string($authorization) || trim($authorization) === '') {
            return null;
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches)) {
            return null;
        }

        $token = trim($matches[1]);

        return $token !== '' ? $token : null;
    }

    private function findByHash(string $hash): ?array
    {
        $result = Model::getDatabaseDriver()->statement(
            'SELECT * FROM api_tokens WHERE token_hash = :hash LIMIT 1',
            [':hash' => $hash]
        );

        return $result[0] ?? null;
    }

    private function isUsable(array $token): bool
    {
        if (!empty($token['revoked_at'])) {
            return false;
        }

        if (!empty($token['expires_at'])) {
            return strtotime((string) $token['expires_at']) >= time();
        }

        return true;
    }

    private function resolveTokenUser(array $token): ?Authenticatable
    {
        $class = $token['tokenable_type'] ?? null;
        $id = $token['tokenable_id'] ?? null;

        if (!is_string($class) || $class === '' || !$id || !class_exists($class)) {
            return null;
        }

        if (!is_subclass_of($class, Authenticatable::class)) {
            return null;
        }

        return $class::find($id);
    }

    private function markAsUsed(int $id): void
    {
        Model::getDatabaseDriver()->statement(
            'UPDATE api_tokens SET last_used_at = :last_used_at, updated_at = :updated_at WHERE id = :id',
            [
                ':id'           => $id,
                ':last_used_at' => $this->now(),
                ':updated_at'   => $this->now(),
            ]
        );
    }

    private function makePlainToken(): string
    {
        return self::PREFIX . bin2hex(random_bytes(32));
    }

    private function hash(string $plainToken): string
    {
        return hash('sha256', trim($plainToken));
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
