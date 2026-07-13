<?php
namespace Whis\Session;

use RuntimeException;

class PhpNativeSessionStorage implements SessionStorage
{
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (session_status() === PHP_SESSION_DISABLED) {
            throw new RuntimeException('PHP sessions are disabled.');
        }

        if (headers_sent($file, $line)) {
            throw new RuntimeException(
                "Cannot start session: headers already sent in {$file} on line {$line}."
            );
        }

        $this->configureSession();

        if (! session_start()) {
            throw new RuntimeException('Failed to start session.');
        }
    }

    public function save(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public function id(): string
    {
        return session_id();
    }

    public function regenerate(bool $deleteOldSession = true): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->start();
        }

        return session_regenerate_id($deleteOldSession);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $_SESSION ?? [])
            ? $_SESSION[$key]
            : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION ?? []);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'] ?: '/',
                'domain'   => $params['domain'] ?? '',
                'secure'   => (bool) ($params['secure'] ?? false),
                'httponly' => (bool) ($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?: 'Lax',
            ]);
        }

        session_destroy();
    }

    private function configureSession(): void
    {
        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.use_only_cookies', '1');
        @ini_set('session.use_trans_sid', '0');
        @ini_set('session.cookie_httponly', '1');

        $name = $this->env('SESSION_NAME', 'WHIS_SESSION');
        $name = preg_replace('/[^A-Za-z0-9_-]/', '_', $name) ?: 'WHIS_SESSION';

        if (preg_match('/^[0-9]+$/', $name) === 1) {
            $name = 'WHIS_' . $name;
        }

        session_name($name);

        $sameSite = ucfirst(strtolower($this->env('SESSION_SAME_SITE', 'Lax')));

        if (! in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            $sameSite = 'Lax';
        }

        $secure = $this->secureCookie();

        if ($sameSite === 'None' && ! $secure) {
            throw new RuntimeException('SESSION_SAME_SITE=None requires secure session cookies.');
        }

        session_set_cookie_params([
            'lifetime' => max(0, (int) $this->env('SESSION_LIFETIME', '0')),
            'path'     => $this->env('SESSION_COOKIE_PATH', '/'),
            'domain'   => $this->env('SESSION_COOKIE_DOMAIN', ''),
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => $sameSite,
        ]);
    }

    private function secureCookie(): bool
    {
        $explicit = $this->env('SESSION_SECURE_COOKIE', '');

        if ($explicit !== '') {
            return $this->toBoolean($explicit, false);
        }

        $appUrl = strtolower($this->env('APP_URL', ''));
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));

        return str_starts_with($appUrl, 'https://')
            || ($https !== '' && $https !== 'off' && $https !== '0');
    }

    private function env(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return is_string($value) ? trim($value) : $default;
    }

    private function toBoolean(string $value, bool $default): bool
    {
        return match (strtolower(trim($value))) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => $default,
        };
    }
}
