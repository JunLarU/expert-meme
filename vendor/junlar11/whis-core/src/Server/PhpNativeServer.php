<?php
namespace Whis\Server;

use RuntimeException;
use Whis\Http\HttpMethod;
use Whis\Http\Request;
use Whis\Http\Response;
use Whis\Storage\File;

class PhpNativeServer implements Server
{
    private bool $headRequest = false;

    protected function uploadedFiles(): array
    {
        $files = [];

        foreach ($_FILES as $key => $file) {
            if (! is_array($file)) {
                continue;
            }

            if (is_array($file['name'] ?? null)) {
                $files[$key] = [];

                foreach ($file['name'] as $index => $name) {
                    $uploadedFile = $this->makeUploadedFile($file, $index);

                    if ($uploadedFile !== null) {
                        $files[$key][] = $uploadedFile;
                    }
                }

                if ($files[$key] === []) {
                    unset($files[$key]);
                }

                continue;
            }

            $uploadedFile = $this->makeUploadedFile($file);

            if ($uploadedFile !== null) {
                $files[$key] = $uploadedFile;
            }
        }

        return $files;
    }

    private function makeUploadedFile(array $file, ?int $index = null): ?File
    {
        $isMultiple = $index !== null;

        $name = $isMultiple
            ? (string) ($file['name'][$index] ?? '')
            : (string) ($file['name'] ?? '');

        $type = $isMultiple
            ? (string) ($file['type'][$index] ?? '')
            : (string) ($file['type'] ?? '');

        $tmpName = $isMultiple
            ? (string) ($file['tmp_name'][$index] ?? '')
            : (string) ($file['tmp_name'] ?? '');

        $size = $isMultiple
            ? (int) ($file['size'][$index] ?? 0)
            : (int) ($file['size'] ?? 0);

        $error = $isMultiple
            ? (int) ($file['error'][$index] ?? UPLOAD_ERR_NO_FILE)
            : (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE || $name === '') {
            return null;
        }

        $content = '';

        if ($error === UPLOAD_ERR_OK && $tmpName !== '' && is_uploaded_file($tmpName)) {
            $content = file_get_contents($tmpName) ?: '';
        }

        return new File(
            $content,
            $type,
            basename(str_replace('\\', '/', $name)),
            $size,
            $error
        );
    }

    protected function requestData(): array
    {
        $headers = $this->headers();
        $contentType = (string) (
            $_SERVER['CONTENT_TYPE']
            ?? $_SERVER['HTTP_CONTENT_TYPE']
            ?? $headers['Content-Type']
            ?? ''
        );

        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');

            if ($raw === false || trim($raw) === '') {
                return [];
            }

            $data = json_decode($raw, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);

            return is_array($data) ? $data : [];
        }

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            return is_array($_POST) ? $_POST : [];
        }

        $raw = file_get_contents('php://input');
        parse_str(is_string($raw) ? $raw : '', $data);

        return is_array($data) ? $data : [];
    }

    public function getRequest(): Request
    {
        $methodName = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        if ($methodName === 'HEAD') {
            $this->headRequest = true;
            $methodName = 'GET';
        }

        $method = HttpMethod::tryFrom($methodName);

        if ($method === null) {
            throw new RuntimeException("Unsupported HTTP method [{$methodName}].");
        }

        return (new Request())
            ->setUri($this->currentUri())
            ->setMethod($method)
            ->setHeaders($this->headers())
            ->setData($this->requestData())
            ->setQuery(is_array($_GET) ? $_GET : [])
            ->setFiles($this->uploadedFiles());
    }

    public function sendResponse(Response $response): void
    {
        $this->applySecurityHeaders($response);
        $response->prepare();

        if (headers_sent($file, $line)) {
            throw new RuntimeException(
                "Cannot send response: headers already sent in {$file} on line {$line}."
            );
        }

        http_response_code($response->status());

        foreach ($response->headers() as $header => $value) {
            header($header . ': ' . $value, true);
        }

        if (! $this->headRequest) {
            echo $response->content() ?? '';
        }
    }

    private function applySecurityHeaders(Response $response): void
    {
        $defaults = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options'        => 'SAMEORIGIN',
            'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(self)',
            'Cross-Origin-Opener-Policy' => 'same-origin-allow-popups',
        ];

        foreach ($defaults as $header => $value) {
            if ($response->headers($header) === null) {
                $response->setHeader($header, $value);
            }
        }

        if ($this->isProduction() && $this->isHttps() && $response->headers('Strict-Transport-Security') === null) {
            $response->setHeader(
                'Strict-Transport-Security',
                'max-age=31536000'
            );
        }
    }

    private function headers(): array
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        if (! is_array($headers)) {
            $headers = [];
        }

        foreach ($_SERVER as $key => $value) {
            if (! str_starts_with($key, 'HTTP_') || ! is_scalar($value)) {
                continue;
            }

            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
            $headers[$name] ??= (string) $value;
        }

        return $headers;
    }

    private function currentUri(): string
    {
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($requestUri, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return '/';
        }

        return '/' . trim($path, '/');
    }

    private function isHttps(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));

        return $https !== '' && $https !== 'off' && $https !== '0';
    }

    private function isProduction(): bool
    {
        $value = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';

        return in_array(strtolower((string) $value), ['prod', 'production'], true);
    }
}
