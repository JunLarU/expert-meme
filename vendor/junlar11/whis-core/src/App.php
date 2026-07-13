<?php
namespace Whis;

use Dotenv\Dotenv;
use ErrorException;
use RuntimeException;
use Throwable;
use Whis\Config\Config;
use Whis\Database\Drivers\DatabaseDriver;
use Whis\Database\Model;
use Whis\Exceptions\HttpNotFoundException;
use Whis\Http\HttpMethod;
use Whis\Http\Request;
use Whis\Http\Response;
use Whis\Routing\Router;
use Whis\Server\Server;
use Whis\Session\Session;
use Whis\Session\SessionStorage;
use Whis\Validation\Exceptions\ValidationException;
use Whis\View\ViewEngine;

class App
{
    public static string $root;

    private static ?self $instance = null;
    private static bool $shutdownHandled = false;

    public ?Router $router = null;
    public ?Request $request = null;
    public ?Server $server = null;
    public ?ViewEngine $viewEngine = null;
    public ?Session $session = null;
    public ?DatabaseDriver $database = null;

    private bool $handlingThrowable = false;
    private ?string $requestId = null;

    public static function bootstrap(string $root): self
    {
        self::$root = rtrim(str_replace('\\', '/', $root), '/');

        // Nunca dependas de display_errors del servidor para proteger producción.
        error_reporting(E_ALL);
        @ini_set('display_errors', '0');
        @ini_set('display_startup_errors', '0');
        @ini_set('html_errors', '0');
        @ini_set('log_errors', '1');

        /** @var self $app */
        $app = singleton(self::class);
        self::$instance = $app;

        $app->registerGlobalHandlers();

        try {
            return $app
                ->loadConfig()
                ->configurePhpRuntime()
                ->runServiceProviders('boot')
                ->setHttpHandlers()
                ->setupDatabaseConnection()
                ->runServiceProviders('runtime');
        } catch (Throwable $e) {
            $app->handleThrowable($e);
        }
    }

    public function run(): void
    {
        try {
            if ($this->router === null || $this->request === null) {
                throw new RuntimeException('Application HTTP services are not initialized.');
            }

            $this->terminate($this->router->resolve($this->request));
        } catch (HttpNotFoundException) {
            $this->terminate($this->buildErrorResponse(404, 'Página no encontrada.'));
        } catch (ValidationException $e) {
            if ($this->expectsJson()) {
                $this->terminate(
                    Response::json([
                        'ok'      => false,
                        'message' => 'Revisa los campos enviados.',
                        'errors'  => $e->errors(),
                    ])->setStatus(422)
                );
            }

            $this->terminate(back()->withErrors($e->errors(), 422));
        } catch (Throwable $e) {
            $this->handleThrowable($e);
        }
    }

    public function prepareNextRequest(): void
    {
        if (
            $this->request !== null
            && $this->session !== null
            && $this->request->method() === HttpMethod::GET
        ) {
            $this->session->set('_previous', $this->request->uri());
        }
    }

    public function abort(Response $response): never
    {
        $this->terminate($response);
    }

    protected function terminate(Response $response): never
    {
        try {
            $this->prepareNextRequest();
        } catch (Throwable $e) {
            $this->logThrowable($e, 'request-finalization');
        }

        $this->closeResources();
        $this->sendResponse($response);
        exit;
    }

    protected function runServiceProviders(string $type): self
    {
        $providers = config('providers.' . $type);

        if ($providers === null) {
            return $this;
        }

        if (! is_array($providers)) {
            throw new RuntimeException("Provider group [{$type}] must be an array.");
        }

        foreach ($providers as $providerClass) {
            if (! is_string($providerClass) || ! class_exists($providerClass)) {
                $providerName = is_string($providerClass)
                    ? $providerClass
                    : get_debug_type($providerClass);

                throw new RuntimeException("Service provider [{$providerName}] was not found.");
            }

            $provider = new $providerClass();

            if (! method_exists($provider, 'registerServices')) {
                throw new RuntimeException(
                    "Service provider [{$providerClass}] must implement registerServices()."
                );
            }

            $provider->registerServices();
        }

        return $this;
    }

    protected function setHttpHandlers(): self
    {
        $this->router  = singleton(Router::class);
        $this->server  = app(Server::class);
        $this->request = singleton(Request::class, fn() => $this->server->getRequest());
        $this->session = singleton(
            Session::class,
            fn() => new Session(app(SessionStorage::class))
        );

        return $this;
    }

    protected function setupDatabaseConnection(): self
    {
        $this->database = app(DatabaseDriver::class);

        $this->database->connect(
            config('database.connection'),
            config('database.host'),
            config('database.port'),
            config('database.database'),
            config('database.username'),
            config('database.password')
        );

        Model::setDatabaseDriver($this->database);

        return $this;
    }

    protected function loadConfig(): self
    {
        // safeLoad permite usar variables reales del servidor sin exigir un .env físico.
        Dotenv::createImmutable(self::$root)->safeLoad();
        Config::load(self::$root . '/config');

        return $this;
    }

    protected function configurePhpRuntime(): self
    {
        $logDirectory = self::$root . '/storage/logs';

        if (! is_dir($logDirectory) && ! @mkdir($logDirectory, 0775, true) && ! is_dir($logDirectory)) {
            // PHP conservará el error_log definido por el servidor.
            return $this;
        }

        @ini_set('error_log', $logDirectory . '/php-error.log');
        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.use_only_cookies', '1');
        @ini_set('session.use_trans_sid', '0');

        return $this;
    }

    private function registerGlobalHandlers(): void
    {
        set_exception_handler(function (Throwable $e): void {
            $app = self::$instance;

            if ($app !== null) {
                $app->handleThrowable($e);
            }

            self::emergencyOutput($e, false);
        });

        register_shutdown_function(function (): void {
            if (self::$shutdownHandled) {
                return;
            }

            $error = error_get_last();

            if ($error === null || ! in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            self::$shutdownHandled = true;

            $exception = new ErrorException(
                (string) ($error['message'] ?? 'Fatal PHP error.'),
                0,
                (int) ($error['type'] ?? E_ERROR),
                (string) ($error['file'] ?? ''),
                (int) ($error['line'] ?? 0)
            );

            $app = self::$instance;

            if ($app !== null) {
                $app->handleThrowable($exception);
            }

            self::emergencyOutput($exception, false);
        });
    }

    private function handleThrowable(Throwable $e): never
    {
        if ($this->handlingThrowable) {
            self::emergencyOutput($e, $this->shouldShowErrors());
        }

        $this->handlingThrowable = true;
        self::$shutdownHandled = true;

        $requestId = $this->requestId();
        $this->logThrowable($e, 'uncaught', $requestId);

        try {
            $response = $this->buildThrowableResponse($e, $requestId);
            $this->closeResources();
            $this->sendResponse($response);
        } catch (Throwable $handlerError) {
            $this->logThrowable($handlerError, 'exception-handler', $requestId);
            self::emergencyOutput($e, $this->shouldShowErrors(), $requestId);
        }

        exit;
    }

    private function buildThrowableResponse(Throwable $e, string $requestId): Response
    {
        if ($this->expectsJson()) {
            $payload = [
                'ok'         => false,
                'message'    => 'Error interno del servidor.',
                'request_id' => $requestId,
            ];

            if ($this->shouldShowErrors()) {
                $payload['debug'] = [
                    'type'    => $e::class,
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => explode("\n", $e->getTraceAsString()),
                ];
            }

            return Response::json($payload)
                ->setStatus(500)
                ->setHeader('X-Request-ID', $requestId)
                ->noStore();
        }

        if ($this->shouldShowErrors()) {
            return Response::text(
                "Error interno ({$requestId})\n\n" .
                'Type: ' . $e::class . "\n" .
                'Message: ' . $e->getMessage() . "\n" .
                'File: ' . $e->getFile() . "\n" .
                'Line: ' . $e->getLine() . "\n\n" .
                $e->getTraceAsString()
            )
                ->setStatus(500)
                ->setHeader('X-Request-ID', $requestId)
                ->noStore();
        }

        return $this->buildErrorResponse(
            500,
            'Ocurrió un error interno. Intenta nuevamente más tarde.',
            $requestId
        );
    }

    private function buildErrorResponse(int $status, string $message, ?string $requestId = null): Response
    {
        $title = $status === 404 ? 'Página no encontrada' : 'Error interno';

        try {
            $response = Response::view(
                'errors/error',
                [
                    'code'       => $status,
                    'text'       => $message,
                    'requestId'  => $requestId,
                    'request_id' => $requestId,
                ],
                'error',
                "Error {$status}"
            )->setStatus($status);
        } catch (Throwable $viewError) {
            $this->logThrowable($viewError, 'error-view', $requestId);

            $requestReference = $requestId === null
                ? ''
                : '<p class="reference">Referencia: <code>' . htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8') . '</code></p>';

            $response = Response::html(
                '<!doctype html><html lang="es"><head><meta charset="utf-8">' .
                '<meta name="viewport" content="width=device-width,initial-scale=1">' .
                '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>' .
                '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:system-ui,sans-serif;background:#f4f6fa;color:#1e293b}' .
                'main{max-width:42rem;padding:2rem;text-align:center}h1{font-size:clamp(3rem,10vw,7rem);margin:0}.reference{font-size:.875rem;color:#64748b}code{word-break:break-all}</style>' .
                '</head><body><main><h1>' . $status . '</h1><h2>' .
                htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2><p>' .
                htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>' .
                $requestReference . '</main></body></html>'
            )->setStatus($status);
        }

        if ($requestId !== null) {
            $response->setHeader('X-Request-ID', $requestId);
        }

        return $response->noStore();
    }

    private function closeResources(): void
    {
        if ($this->session !== null) {
            try {
                $this->session->close();
            } catch (Throwable $e) {
                $this->logThrowable($e, 'session-close');
            }
        }

        if ($this->database !== null) {
            try {
                $this->database->close();
            } catch (Throwable $e) {
                $this->logThrowable($e, 'database-close');
            }
        }
    }

    private function sendResponse(Response $response): void
    {
        if ($this->server !== null) {
            $this->server->sendResponse($response);
            return;
        }

        if (! headers_sent()) {
            http_response_code($response->status());

            foreach ($response->headers() as $header => $value) {
                header($header . ': ' . $value, true);
            }
        }

        echo $response->content() ?? '';
    }

    private function expectsJson(): bool
    {
        if ($this->request !== null) {
            return $this->request->expectsJson();
        }

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

        return str_contains($accept, 'application/json')
            || str_contains($accept, '+json')
            || str_contains($contentType, 'application/json')
            || $requestedWith === 'xmlhttprequest';
    }

    private function shouldShowErrors(): bool
    {
        return $this->isDevelopmentEnvironment()
            && $this->envBoolean('APP_SHOW_ERROR', false);
    }

    private function isDevelopmentEnvironment(): bool
    {
        $environment = strtolower(trim($this->env('APP_ENV', 'production')));

        return in_array($environment, ['dev', 'development', 'local'], true);
    }

    private function envBoolean(string $key, bool $default): bool
    {
        $value = strtolower(trim($this->env($key, $default ? 'true' : 'false')));

        return match ($value) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off', '' => false,
            default => $default,
        };
    }

    private function env(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return is_string($value) ? $value : $default;
    }

    private function requestId(): string
    {
        if ($this->requestId !== null) {
            return $this->requestId;
        }

        try {
            $this->requestId = bin2hex(random_bytes(12));
        } catch (Throwable) {
            $this->requestId = str_replace('.', '', uniqid('req_', true));
        }

        return $this->requestId;
    }

    private function logThrowable(Throwable $e, string $context, ?string $requestId = null): void
    {
        try {
            $directory = self::$root . '/storage/logs';

            if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
                error_log($e->__toString());
                return;
            }

            $requestId ??= $this->requestId();
            $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

            $message = sprintf(
                "[%s] %s.%s request_id=%s ip=%s method=%s uri=%s\n%s\n\n",
                date('Y-m-d H:i:s'),
                $context,
                $e::class,
                $requestId,
                $ip,
                $method,
                $uri,
                $e->__toString()
            );

            $message = $this->redactSecrets($message);

            @file_put_contents(
                $directory . '/whis-' . date('Y-m-d') . '.log',
                $message,
                FILE_APPEND | LOCK_EX
            );
        } catch (Throwable) {
            error_log($e->__toString());
        }
    }

    private function redactSecrets(string $value): string
    {
        $environment = array_merge(
            is_array($_SERVER) ? $_SERVER : [],
            is_array($_ENV) ? $_ENV : []
        );

        foreach ($environment as $key => $secret) {
            if (! is_string($key) || ! is_scalar($secret)) {
                continue;
            }

            if (! preg_match('/(?:PASSWORD|PASS|SECRET|TOKEN|API_KEY|PRIVATE_KEY|AUTH)/i', $key)) {
                continue;
            }

            $secret = (string) $secret;

            if (strlen($secret) >= 4) {
                $value = str_replace($secret, '[REDACTED]', $value);
            }
        }

        return $value;
    }

    private static function emergencyOutput(
        Throwable $e,
        bool $showDetails,
        ?string $requestId = null
    ): never {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        if (! headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8', true);
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
            header('X-Content-Type-Options: nosniff', true);
        }

        $message = 'Error interno del servidor.';

        if ($requestId !== null) {
            $message .= "\nReferencia: {$requestId}";
        }

        if ($showDetails) {
            $message .= "\n\n" . $e::class . ': ' . $e->getMessage();
            $message .= "\n" . $e->getFile() . ':' . $e->getLine();
        }

        echo $message;
        exit;
    }
}
