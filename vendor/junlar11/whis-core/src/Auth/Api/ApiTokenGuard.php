<?php
namespace Whis\Auth\Api;

use Whis\Auth\Authenticatable;
use Whis\Database\Model;
use Whis\Http\Request;

class ApiTokenGuard
{
    private const PREFIX        = 'whis_';
    private const SYSTEM_PREFIX = 'sys_';

    /**
     * @var string|null IP del cliente (se inyecta desde Request)
     */
    protected ?string $clientIp = null;

    /**
     * @var string|null User Agent del cliente
     */
    protected ?string $userAgent = null;

    public function __construct(?Request $request = null)
    {
        if ($request) {
            $this->clientIp = $request->headers('x-forwarded-for')
                ?: ($_SERVER['REMOTE_ADDR'] ?? null);
            $this->userAgent = $request->headers('user-agent')
                ?: ($_SERVER['HTTP_USER_AGENT'] ?? null);
        }
    }

    /**
     * Emitir un nuevo token para un usuario.
     */
    public function issue(
        ?Authenticatable $user,
        string $name = 'API Token',
        array $abilities = [],
        ? \DateTimeInterface $expiresAt = null,
        bool $system = false
    ) : array {
        $plainToken = $this->makePlainToken($system);
        $now        = $this->now();

        $abilities = $this->sanitizeIssuedAbilities($abilities, $system);

        $record = [
            'tokenable_type'       => $user ? get_class($user) : null,
            'tokenable_id'         => $user?->getKey(),
            'name'                 => trim($name) ?: 'API Token',
            'token_prefix'         => substr($plainToken, 0, 22),
            'token_hash'           => $this->hash($plainToken),
            'abilities'            => json_encode(array_values($abilities), JSON_UNESCAPED_UNICODE),
            'expires_at'           => $expiresAt?->format('Y-m-d H:i:s'),
            'last_used_at'         => null,
            'last_used_ip'         => null,
            'last_used_user_agent' => null,
            'revoked_at'           => null,
            'created_at'           => $now,
            'updated_at'           => null,
            'system'               => $system ? 1 : 0,
        ];

        Model::getDatabaseDriver()->statement(
            'INSERT INTO api_tokens
            (tokenable_type, tokenable_id, name, token_prefix, token_hash, abilities, expires_at, last_used_at, last_used_ip, last_used_user_agent, revoked_at, created_at, updated_at, system)
         VALUES
            (:tokenable_type, :tokenable_id, :name, :token_prefix, :token_hash, :abilities, :expires_at, :last_used_at, :last_used_ip, :last_used_user_agent, :revoked_at, :created_at, :updated_at, :system)',
            $record
        );

        return [
            'plainTextToken' => $plainToken,
            'token'          => $this->findByHash($this->hash($plainToken)),
        ];
    }

    private function sanitizeIssuedAbilities(array $abilities, bool $system = false): array
    {
        $abilities = array_values(array_unique(array_filter(
            array_map(fn($ability) => strtolower(trim((string) $ability)), $abilities),
            fn($ability) => $ability !== ''
        )));

        /*
     * Solo los tokens de sistema pueden caer a "*" por default.
     * Los tokens normales sin permisos quedan sin abilities.
     */
        if (empty($abilities)) {
            return $system ? ['*'] : [];
        }

        if (in_array('*', $abilities, true)) {
            return ['*'];
        }

        return $abilities;
    }

    /**
     * Crea un token interno para el sistema (ej: microservicios, cron, etc.)
     */
    public function createSystemToken(
        string $name = 'System Token',
        array $abilities = ['*'],
        ? \DateTimeInterface $expiresAt = null
    ) : array {
        return $this->issue(null, $name, $abilities, $expiresAt, true);
    }

    /**
     * Autentica un request usando Bearer token.
     */
    public function authenticate(Request $request): ?ApiTokenResult
    {
        $plainToken = $this->bearerToken($request);

        if ($plainToken === null) {
            return null;
        }

        $token = $this->findByHash($this->hash($plainToken));

        if (! $token || ! $this->isUsable($token)) {
            return null;
        }

        // Marcar uso (actualizar last_used_at, ip, user_agent)
        $this->markAsUsed((int) $token['id'], $request);

        return new ApiTokenResult(
            token: $token,
            user: $this->resolveTokenUser($token)
        );
    }

    /**
     * Revoca un token por su ID.
     */
    public function revokeById(int | string $id): bool
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

    /**
     * Revoca todos los tokens de un usuario.
     */
    public function revokeUserTokens(Authenticatable $user): void
    {
        Model::getDatabaseDriver()->statement(
            'UPDATE api_tokens
             SET revoked_at = :revoked_at, updated_at = :updated_at
             WHERE tokenable_type = :type AND tokenable_id = :id AND revoked_at IS NULL',
            [
                ':type'       => get_class($user),
                ':id'         => $user->getKey(),
                ':revoked_at' => $this->now(),
                ':updated_at' => $this->now(),
            ]
        );
    }

    /**
     * Obtiene todos los tokens de un usuario (no revocados, no expirados).
     */
    public function tokensFor(?Authenticatable $user, bool $includeRevoked = false): array
    {
        if (! $user) {
            return [];
        }

        $sql = 'SELECT id, tokenable_type, tokenable_id, name, token_prefix, abilities, expires_at, last_used_at, last_used_ip, last_used_user_agent, revoked_at, created_at, updated_at, system
                FROM api_tokens
                WHERE tokenable_type = :type AND tokenable_id = :id';
        if (! $includeRevoked) {
            $sql .= ' AND revoked_at IS NULL';
        }
        $sql .= ' ORDER BY id DESC';

        return Model::getDatabaseDriver()->statement($sql, [
            ':type' => get_class($user),
            ':id'   => $user->getKey(),
        ]);
    }

    /**
     * Verifica si un token posee una habilidad específica.
     */
    public function tokenCan(array $token, string $ability): bool
    {
        $abilities = $this->abilities($token);
        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    /**
     * Obtiene las habilidades de un token como array.
     */
    public function abilities(array $token): array
    {
        $abilities = json_decode((string) ($token['abilities'] ?? '[]'), true);
        if (! is_array($abilities)) {
            return [];
        }
        return array_values(array_filter(
            array_map(fn($a) => trim((string) $a), $abilities),
            fn($a) => $a !== ''
        ));
    }

    /**
     * Extrae el token Bearer de la cabecera Authorization.
     */
    public function bearerToken(Request $request): ?string
    {
        $authorization = $request->headers('authorization')
            ?: $request->headers('Authorization')
            ?: ($_SERVER['HTTP_AUTHORIZATION'] ?? null)
            ?: ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null);

        if (! $authorization && function_exists('getallheaders')) {
            $headers       = getallheaders();
            $authorization = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        }

        if (! is_string($authorization) || trim($authorization) === '') {
            return null;
        }

        if (! preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches)) {
            return null;
        }

        $token = trim($matches[1]);
        return $token !== '' ? $token : null;
    }

    // ====== Métodos privados ======

    private function makePlainToken(bool $system = false): string
    {
        $prefix = $system ? self::SYSTEM_PREFIX : self::PREFIX;
        // 32 bytes = 64 caracteres hex + prefijo
        return $prefix . bin2hex(random_bytes(32));
    }

    private function hash(string $plainToken): string
    {
        return hash('sha256', trim($plainToken));
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
        if (! empty($token['revoked_at'])) {
            return false;
        }
        if (! empty($token['expires_at'])) {
            return strtotime((string) $token['expires_at']) >= time();
        }
        return true;
    }

    private function resolveTokenUser(array $token): ?Authenticatable
    {
        $class = $token['tokenable_type'] ?? null;
        $id    = $token['tokenable_id'] ?? null;
        if (! is_string($class) || $class === '' || ! $id || ! class_exists($class)) {
            return null;
        }
        if (! is_subclass_of($class, Authenticatable::class)) {
            return null;
        }
        return $class::find($id);
    }

    private function markAsUsed(int $id, Request $request): void
    {
        $params = [
            ':id'           => $id,
            ':last_used_at' => $this->now(),
            ':updated_at'   => $this->now(),
        ];
        if ($this->clientIp) {
            $params[':last_used_ip'] = $this->clientIp;
        }
        if ($this->userAgent) {
            $params[':last_used_user_agent'] = substr($this->userAgent, 0, 255);
        }

        $set = 'last_used_at = :last_used_at, updated_at = :updated_at';
        if (isset($params[':last_used_ip'])) {
            $set .= ', last_used_ip = :last_used_ip';
        }
        if (isset($params[':last_used_user_agent'])) {
            $set .= ', last_used_user_agent = :last_used_user_agent';
        }

        Model::getDatabaseDriver()->statement(
            "UPDATE api_tokens SET $set WHERE id = :id",
            $params
        );
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
