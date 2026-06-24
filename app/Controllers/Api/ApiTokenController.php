<?php

namespace App\Controllers\Api;

use App\Models\User;
use DateTimeImmutable;
use Throwable;
use Whis\Auth\Api\ApiTokenGuard;
use Whis\Database\Model;
use Whis\Http\Controller;
use Whis\Http\Request;
use Whis\Http\Response;

class ApiTokenController extends Controller
{
    private const ADMIN_BASE = '/admin/api-tokens';
    private const ADMIN_LIMIT = 200;
    private const ABILITY_PATTERN = '/^[a-z][a-z0-9._:-]{1,80}$/i';

    private array $fallbackAbilityPresets = [
        '*'              => 'Acceso total',
        'tokens:read'    => 'Ver tokens',
        'tokens:create'  => 'Crear tokens',
        'tokens:update'  => 'Editar tokens',
        'tokens:delete'  => 'Eliminar tokens',
        'projects:read'  => 'Ver proyectos',
        'projects:write' => 'Modificar proyectos',
        'messages:read'  => 'Ver mensajes',
        'clients:read'   => 'Ver clientes',
    ];

    public function index()
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        $tokens = $this->hydrateTokens($this->tokenRows());
        $users  = $this->users();

        return view('pages/admin/api/index', 'API y Tokens', [
            'tokens'           => $tokens,
            'users'            => $users,
            'abilityPresets'   => $this->abilityPresets(),
            'defaultAbilities' => $this->defaultAbilities(),
            'stats'            => $this->stats($tokens),
            'currentUser'      => auth(),
        ], 'layouts/admin/layout');
    }

    public function show(int|string $id)
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        $token = $this->findToken($id);

        if (! $token) {
            return redirect(self::ADMIN_BASE);
        }

        return view('pages/admin/api/show', 'Detalle de token', [
            'token'          => $this->hydrateToken($token),
            'abilityPresets' => $this->abilityPresets(),
        ], 'layouts/admin/layout');
    }

    public function edit(int|string $id)
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        $token = $this->findToken($id);

        if (! $token) {
            return redirect(self::ADMIN_BASE);
        }

        if ((int) ($token['system'] ?? 0) === 1) {
            return redirect(self::ADMIN_BASE);
        }

        return view('pages/admin/api/edit', 'Editar token', [
            'token'          => $this->hydrateToken($token),
            'abilityPresets' => $this->abilityPresets(),
        ], 'layouts/admin/layout');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->requireAdmin($request)) {
            return $response;
        }

        $data   = $this->requestData($request);
        $errors = $this->validateCreate($data);

        if ($errors) {
            return $this->jsonValidation($errors);
        }

        $user = $this->resolveUser($data['tokenable_id'] ?? null);

        if (! $user) {
            return $this->jsonValidation([
                'tokenable_id' => 'Selecciona un usuario válido para asignar el token.',
            ]);
        }

        $issued = $this->tokenGuard()->issue(
            user: $user,
            name: $this->cleanName($data['name'] ?? ''),
            abilities: $this->normalizeAbilities($data),
            expiresAt: $this->expiresAt($data['expires_at'] ?? null)
        );

        return Response::json([
            'ok'             => true,
            'message'        => 'Token creado correctamente. Cópialo ahora; no se volverá a mostrar.',
            'plainTextToken' => $issued['plainTextToken'] ?? null,
            'token'          => $this->publicToken($issued['token'] ?? null),
        ])->setStatus(201);
    }

    public function update(Request $request, int|string $id): Response
    {
        if ($response = $this->requireAdmin($request)) {
            return $response;
        }

        $token = $this->findToken($id);

        if (! $token) {
            return $this->jsonError('El token no existe.', 404);
        }

        if ((int) ($token['system'] ?? 0) === 1) {
            return $this->jsonError('Los tokens de sistema no se pueden editar desde el panel.', 403);
        }

        $data   = $this->requestData($request);
        $errors = $this->validateUpdate($data, $id);

        if ($errors) {
            return $this->jsonValidation($errors);
        }

        Model::getDatabaseDriver()->statement(
            'UPDATE api_tokens
             SET name = :name,
                 abilities = :abilities,
                 expires_at = :expires_at,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                ':id'         => $id,
                ':name'       => $this->cleanName($data['name'] ?? ''),
                ':abilities'  => json_encode($this->normalizeAbilities($data), JSON_UNESCAPED_UNICODE),
                ':expires_at' => $this->expiresAtString($data['expires_at'] ?? null),
                ':updated_at' => $this->now(),
            ]
        );

        return Response::json([
            'ok'       => true,
            'message'  => 'Token actualizado correctamente.',
            'redirect' => self::ADMIN_BASE . '/' . $id,
        ]);
    }

    public function revoke(Request $request, int|string $id): Response
    {
        if ($response = $this->requireAdmin($request)) {
            return $response;
        }

        $token = $this->findToken($id);

        if (! $token) {
            return $this->jsonError('El token no existe.', 404);
        }

        if ((int) ($token['system'] ?? 0) === 1) {
            return $this->jsonError('Los tokens de sistema no pueden revocarse manualmente.', 403);
        }

        $this->tokenGuard()->revokeById($id);

        return Response::json([
            'ok'       => true,
            'message'  => 'Token revocado correctamente.',
            'redirect' => self::ADMIN_BASE,
        ]);
    }

    public function destroy(Request $request, int|string $id): Response
    {
        if ($response = $this->requireAdmin($request)) {
            return $response;
        }

        $token = $this->findToken($id);

        if (! $token) {
            return $this->jsonError('El token no existe.', 404);
        }

        if ((int) ($token['system'] ?? 0) === 1) {
            return $this->jsonError('Los tokens de sistema no pueden eliminarse desde el panel.', 403);
        }

        Model::getDatabaseDriver()->statement(
            'DELETE FROM api_tokens WHERE id = :id',
            [':id' => $id]
        );

        return Response::json([
            'ok'       => true,
            'message'  => 'Token eliminado definitivamente.',
            'redirect' => self::ADMIN_BASE,
        ]);
    }

    private function requestData(Request $request): array
    {
        return [
            'tokenable_id'     => $request->data('tokenable_id'),
            'name'             => $request->data('name'),
            'abilities'        => $request->data('abilities'),
            'custom_abilities' => $request->data('custom_abilities'),
            'expires_at'       => $request->data('expires_at'),
        ];
    }

    private function validateCreate(array $data): array
    {
        $errors = $this->validateUpdate($data);

        $user = $this->resolveUser($data['tokenable_id'] ?? null);

        if (! $user) {
            $errors['tokenable_id'] = 'Selecciona un usuario válido.';
        }

        if ($user && $this->activeTokenCountForUser($user) >= (int) $this->cfg('max_tokens_per_user', 50)) {
            $errors['tokenable_id'] = 'Este usuario alcanzó el límite de tokens activos.';
        }

        if ($user && empty($errors['name'])) {
            $existing = Model::getDatabaseDriver()->statement(
                'SELECT id
                 FROM api_tokens
                 WHERE tokenable_type = :type
                   AND tokenable_id = :user_id
                   AND name = :name
                   AND revoked_at IS NULL
                 LIMIT 1',
                [
                    ':type'    => User::class,
                    ':user_id' => $this->modelKey($user),
                    ':name'    => $this->cleanName($data['name'] ?? ''),
                ]
            );

            if (! empty($existing)) {
                $errors['name'] = 'Ya existe un token activo con ese nombre para este usuario.';
            }
        }

        return $errors;
    }

    private function validateUpdate(array $data, int|string|null $ignoreId = null): array
    {
        $errors = [];
        $name   = $this->cleanName($data['name'] ?? '');

        if ($name === '') {
            $errors['name'] = 'Escribe un nombre para identificar el token.';
        } elseif (mb_strlen($name) < 3) {
            $errors['name'] = 'El nombre debe tener al menos 3 caracteres.';
        } elseif (mb_strlen($name) > 120) {
            $errors['name'] = 'El nombre no debe exceder 120 caracteres.';
        } elseif (! preg_match('/^[\pL\pN][\pL\pN\s._-]{2,119}$/u', $name)) {
            $errors['name'] = 'Usa letras, números, espacios, puntos, guiones o guion bajo.';
        }

        $abilityErrors = [];
        $this->normalizeAbilities($data, $abilityErrors);

        if ($abilityErrors) {
            $errors = array_merge($errors, $abilityErrors);
        }

        $expiresAt = trim((string) ($data['expires_at'] ?? ''));

        if ($expiresAt !== '') {
            $date = $this->expiresAt($expiresAt);

            if (! $date) {
                $errors['expires_at'] = 'La fecha de expiración no es válida.';
            } elseif ($date <= new DateTimeImmutable('now')) {
                $errors['expires_at'] = 'La fecha de expiración debe ser futura.';
            }
        }

        return $errors;
    }

    private function normalizeAbilities(array $data, array &$errors = []): array
    {
        $raw = $data['abilities'] ?? [];

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : explode(',', $raw);
        }

        if (! is_array($raw)) {
            $raw = [];
        }

        $customRaw = trim((string) ($data['custom_abilities'] ?? ''));

        if ($customRaw !== '') {
            if (! (bool) $this->cfg('allow_custom_abilities', true)) {
                $errors['custom_abilities'] = 'Los permisos personalizados están desactivados.';
            } else {
                $raw = array_merge($raw, preg_split('/[\s,]+/', $customRaw) ?: []);
            }
        }

        $allowWildcard = (bool) $this->cfg('allow_wildcard', true);
        $allowCustom   = (bool) $this->cfg('allow_custom_abilities', true);
        $presets       = $this->abilityPresets();
        $maxAbilities  = (int) $this->cfg('max_abilities', 50);

        $abilities = [];

        foreach ($raw as $ability) {
            $ability = strtolower(trim((string) $ability));

            if ($ability === '') {
                continue;
            }

            if ($ability === '*') {
                if (! $allowWildcard) {
                    $errors['abilities'] = 'El permiso de acceso total está desactivado.';
                    continue;
                }

                return ['*'];
            }

            if (! preg_match(self::ABILITY_PATTERN, $ability)) {
                $errors['custom_abilities'] = 'Usa permisos con formato tipo modulo:accion, por ejemplo projects:read.';
                continue;
            }

            if (! array_key_exists($ability, $presets) && ! $allowCustom) {
                $errors['abilities'] = 'Uno o más permisos no están permitidos.';
                continue;
            }

            $abilities[] = $ability;
        }

        $abilities = array_values(array_unique($abilities));

        if ((bool) $this->cfg('require_abilities', true) && empty($abilities)) {
            $errors['abilities'] = 'Selecciona al menos un permiso.';
        }

        if (count($abilities) > $maxAbilities) {
            $errors['abilities'] = 'Selecciona máximo ' . $maxAbilities . ' permisos.';
            $abilities = array_slice($abilities, 0, $maxAbilities);
        }

        return $abilities;
    }

    private function tokenRows(): array
    {
        return Model::getDatabaseDriver()->statement(
            'SELECT
                api_tokens.id,
                api_tokens.tokenable_type,
                api_tokens.tokenable_id,
                api_tokens.name,
                api_tokens.token_prefix,
                api_tokens.abilities,
                api_tokens.expires_at,
                api_tokens.last_used_at,
                api_tokens.last_used_ip,
                api_tokens.last_used_user_agent,
                api_tokens.revoked_at,
                api_tokens.created_at,
                api_tokens.updated_at,
                api_tokens.system,
                users.name AS user_name,
                users.email AS user_email
             FROM api_tokens
             LEFT JOIN users
                ON api_tokens.tokenable_type = :user_class
               AND api_tokens.tokenable_id = users.id
             ORDER BY api_tokens.id DESC
             LIMIT ' . self::ADMIN_LIMIT,
            [
                ':user_class' => User::class,
            ]
        );
    }

    private function findToken(int|string $id): ?array
    {
        $result = Model::getDatabaseDriver()->statement(
            'SELECT
                api_tokens.id,
                api_tokens.tokenable_type,
                api_tokens.tokenable_id,
                api_tokens.name,
                api_tokens.token_prefix,
                api_tokens.abilities,
                api_tokens.expires_at,
                api_tokens.last_used_at,
                api_tokens.last_used_ip,
                api_tokens.last_used_user_agent,
                api_tokens.revoked_at,
                api_tokens.created_at,
                api_tokens.updated_at,
                api_tokens.system,
                users.name AS user_name,
                users.email AS user_email
             FROM api_tokens
             LEFT JOIN users
                ON api_tokens.tokenable_type = :user_class
               AND api_tokens.tokenable_id = users.id
             WHERE api_tokens.id = :id
             LIMIT 1',
            [
                ':id'         => $id,
                ':user_class' => User::class,
            ]
        );

        return $result[0] ?? null;
    }

    private function users(): array
    {
        $users = User::all('name') ?? [];

        return array_map(function ($user) {
            if (is_array($user)) {
                return $user;
            }

            return [
                'id'    => $this->modelKey($user),
                'name'  => $user->name ?? 'Usuario',
                'email' => $user->email ?? '',
            ];
        }, $users);
    }

    private function resolveUser(mixed $id): ?User
    {
        if ($id === null || trim((string) $id) === '') {
            return null;
        }

        $user = User::find($id);

        return $user instanceof User ? $user : null;
    }

    private function activeTokenCountForUser(User $user): int
    {
        $result = Model::getDatabaseDriver()->statement(
            'SELECT COUNT(*) AS total
             FROM api_tokens
             WHERE tokenable_type = :type
               AND tokenable_id = :id
               AND revoked_at IS NULL',
            [
                ':type' => User::class,
                ':id'   => $this->modelKey($user),
            ]
        );

        return (int) ($result[0]['total'] ?? 0);
    }

    private function hydrateTokens(array $tokens): array
    {
        return array_map(fn (array $token) => $this->hydrateToken($token), $tokens);
    }

    private function hydrateToken(array $token): array
    {
        $abilities = $this->abilitiesFromJson($token['abilities'] ?? '[]');
        $status    = $this->status($token);

        $token['abilities_array']      = $abilities;
        $token['abilities_label']      = in_array('*', $abilities, true) ? 'Acceso total' : implode(', ', $abilities);
        $token['custom_abilities']     = implode(', ', array_diff($abilities, array_keys($this->abilityPresets())));
        $token['status_key']           = $status['key'];
        $token['status_label']         = $status['label'];
        $token['status_class']         = $status['class'];
        $token['created_at_label']     = $this->dateLabel($token['created_at'] ?? null);
        $token['updated_at_label']     = $this->dateLabel($token['updated_at'] ?? null);
        $token['expires_at_label']     = $this->dateLabel($token['expires_at'] ?? null, 'Sin expiración');
        $token['expires_at_input']     = $this->dateInput($token['expires_at'] ?? null);
        $token['last_used_at_label']   = $this->dateLabel($token['last_used_at'] ?? null, 'Nunca usado');
        $token['revoked_at_label']     = $this->dateLabel($token['revoked_at'] ?? null, 'No revocado');
        $token['user_label']           = trim((string) ($token['user_name'] ?? '')) !== ''
            ? trim((string) $token['user_name'])
            : 'Usuario #' . ($token['tokenable_id'] ?? '—');
        $token['user_email']           = $token['user_email'] ?? '';
        $token['last_used_ip']         = $token['last_used_ip'] ?? '—';
        $token['last_used_user_agent'] = $token['last_used_user_agent'] ?? '—';
        $token['system']               = (int) ($token['system'] ?? 0);

        return $token;
    }

    private function stats(array $tokens): array
    {
        $stats = [
            'total'   => count($tokens),
            'active'  => 0,
            'revoked' => 0,
            'expired' => 0,
        ];

        foreach ($tokens as $token) {
            $key = $token['status_key'] ?? 'active';

            if (isset($stats[$key])) {
                $stats[$key]++;
            }
        }

        return $stats;
    }

    private function status(array $token): array
    {
        if (! empty($token['revoked_at'])) {
            return [
                'key'   => 'revoked',
                'label' => 'Revocado',
                'class' => 'admin-badge--muted',
            ];
        }

        if (! empty($token['expires_at']) && strtotime((string) $token['expires_at']) < time()) {
            return [
                'key'   => 'expired',
                'label' => 'Expirado',
                'class' => 'admin-badge--warning',
            ];
        }

        return [
            'key'   => 'active',
            'label' => 'Activo',
            'class' => 'admin-badge--success',
        ];
    }

    private function abilityPresets(): array
    {
        $configured = $this->cfg('abilities', null);

        return is_array($configured) && ! empty($configured)
            ? $configured
            : $this->fallbackAbilityPresets;
    }

    private function defaultAbilities(): array
    {
        $default = $this->cfg('default_abilities', ['projects:read']);

        return is_array($default) ? $default : ['projects:read'];
    }

    private function abilitiesFromJson(mixed $value): array
    {
        $abilities = json_decode((string) ($value ?: '[]'), true);

        if (! is_array($abilities)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn ($ability) => trim((string) $ability), $abilities),
            fn ($ability) => $ability !== ''
        ));
    }

    private function cleanName(mixed $name): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $name));
    }

    private function expiresAt(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function expiresAtString(mixed $value): ?string
    {
        return $this->expiresAt($value)?->format('Y-m-d H:i:s');
    }

    private function publicToken(?array $token): ?array
    {
        if (! $token) {
            return null;
        }

        unset($token['token_hash']);

        return $this->hydrateToken($token);
    }

    private function dateLabel(mixed $value, string $empty = '—'): string
    {
        if (! is_string($value) || trim($value) === '') {
            return $empty;
        }

        $time = strtotime($value);

        return $time ? date('d/m/Y H:i', $time) : $empty;
    }

    private function dateInput(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return '';
        }

        $time = strtotime($value);

        return $time ? date('Y-m-d\TH:i', $time) : '';
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function jsonValidation(array $errors): Response
    {
        return Response::json([
            'ok'      => false,
            'message' => 'Revisa los campos marcados.',
            'errors'  => $errors,
        ])->setStatus(422);
    }

    private function requireAdmin(?Request $request = null): ?Response
    {
        if (isGuest()) {
            if ($this->expectsAjax($request)) {
                return $this->jsonError('Debes iniciar sesión para administrar tokens.', 401);
            }

            return redirect('/login');
        }

        if (! $this->canManageTokens(auth())) {
            if ($this->expectsAjax($request)) {
                return $this->jsonError('No tienes permisos para administrar tokens.', 403);
            }

            return redirect('/admin');
        }

        return null;
    }

    private function canManageTokens(mixed $user): bool
    {
        if (! $user) {
            return false;
        }

        $allowedRoles = $this->cfg('admin_roles', ['admin', 'super_admin']);

        if (! is_array($allowedRoles)) {
            $allowedRoles = ['admin', 'super_admin'];
        }

        $role = strtolower((string) ($user->role ?? ''));

        return in_array($role, $allowedRoles, true);
    }

    private function expectsAjax(?Request $request = null): bool
    {
        if ($request && method_exists($request, 'expectsJson') && $request->expectsJson()) {
            return true;
        }

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $xhr    = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

        return str_contains($accept, 'application/json') || $xhr === 'xmlhttprequest';
    }

    private function tokenGuard(): ApiTokenGuard
    {
        $guard = app(ApiTokenGuard::class);

        if ($guard instanceof ApiTokenGuard) {
            return $guard;
        }

        if (function_exists('singleton')) {
            $guard = singleton(ApiTokenGuard::class, fn () => new ApiTokenGuard());

            if ($guard instanceof ApiTokenGuard) {
                return $guard;
            }
        }

        return new ApiTokenGuard();
    }

    private function modelKey(mixed $model): mixed
    {
        if (is_array($model)) {
            return $model['id'] ?? null;
        }

        if (is_object($model)) {
            if (method_exists($model, 'getKey')) {
                return $model->getKey();
            }

            if (method_exists($model, 'id')) {
                return $model->id();
            }

            return $model->id ?? null;
        }

        return null;
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        try {
            $value = config('api.tokens.' . $key);

            return $value === null ? $default : $value;
        } catch (Throwable) {
            return $default;
        }
    }
}