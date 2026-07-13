<?php
namespace Whis\Http;

use InvalidArgumentException;
use JsonException;
use Whis\View\ViewEngine;

class Response
{
    protected int $status = 200;

    /** @var array<string,string> */
    protected array $headers = [];

    protected ?string $content = null;

    public function status(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException("Invalid HTTP status code [{$status}].");
        }

        $this->status = $status;
        return $this;
    }

    public function headers(?string $key = null): array|string|null
    {
        if ($key === null) {
            return $this->headers;
        }

        return $this->headers[strtolower($key)] ?? null;
    }

    public function setHeader(string $header, string $value): self
    {
        $header = trim($header);

        if ($header === '' || preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/', $header) !== 1) {
            throw new InvalidArgumentException('Invalid HTTP header name.');
        }

        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new InvalidArgumentException("Invalid value for HTTP header [{$header}].");
        }

        $this->headers[strtolower($header)] = $value;
        return $this;
    }

    public function removeHeader(string $header): self
    {
        unset($this->headers[strtolower($header)]);
        return $this;
    }

    public function setContentType(string $value): self
    {
        return $this->setHeader('Content-Type', $value);
    }

    public function content(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function prepare(): void
    {
        if ($this->content === null) {
            $this->removeHeader('Content-Type');
            $this->removeHeader('Content-Length');
            return;
        }

        $this->setHeader('Content-Length', (string) strlen($this->content));
    }

    public function noStore(): self
    {
        return $this
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('Expires', '0');
    }

    public function publicCache(int $seconds, int $staleWhileRevalidate = 0): self
    {
        $seconds = max(0, $seconds);
        $staleWhileRevalidate = max(0, $staleWhileRevalidate);

        $value = "public, max-age={$seconds}";

        if ($staleWhileRevalidate > 0) {
            $value .= ", stale-while-revalidate={$staleWhileRevalidate}";
        }

        return $this->setHeader('Cache-Control', $value);
    }

    public static function json(array $data): self
    {
        try {
            $content = json_encode(
                $data,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Could not encode JSON response.', 0, $e);
        }

        return (new self())
            ->setContentType('application/json; charset=UTF-8')
            ->setContent($content);
    }

    public static function text(string $text): self
    {
        return (new self())
            ->setContentType('text/plain; charset=UTF-8')
            ->setContent(self::toUtf8($text));
    }

    public static function html(string $html): self
    {
        return (new self())
            ->setContentType('text/html; charset=UTF-8')
            ->setContent(self::toUtf8($html));
    }

    public static function redirect(string $uri, int $status = 302): self
    {
        if (! in_array($status, [301, 302, 303, 307, 308], true)) {
            throw new InvalidArgumentException('Invalid redirect status code.');
        }

        return (new self())
            ->setStatus($status)
            ->setHeader('Location', $uri)
            ->noStore();
    }

    public static function view(
        string $view,
        array|string $parameters = [],
        ?string $layout = null,
        ?string $pageName = null
    ): self {
        if (is_string($parameters)) {
            $pageName = $parameters;
            $parameters = [];
        }

        $content = app(ViewEngine::class)->render(
            $view,
            $parameters,
            $layout,
            $pageName
        );

        // Las vistas son dinámicas por defecto. Las páginas realmente públicas
        // pueden habilitar caché explícitamente con ->publicCache(...).
        return self::html($content)->noStore();
    }

    public function withErrors(array $errors, int $status = 400): self
    {
        $this->setStatus($status);
        session()->flash('_errors', $errors);
        session()->flash('_old', request()->data());

        return $this;
    }

    public static function file(
        string $path,
        bool $download = false,
        ?string $downloadName = null,
        bool $cache = false
    ): self {
        $realPath = realpath($path);

        if ($realPath === false || ! is_file($realPath) || ! is_readable($realPath)) {
            return self::text('404 Not Found')->setStatus(404)->noStore();
        }

        $content = file_get_contents($realPath);

        if ($content === false) {
            return self::text('404 Not Found')->setStatus(404)->noStore();
        }

        $response = (new self())
            ->setStatus(200)
            ->setContentType(self::mimeType($realPath))
            ->setContent($content);

        $cache
            ? $response->publicCache(86400, 604800)
            : $response->noStore();

        if ($download) {
            $filename = self::safeDownloadName($downloadName ?: basename($realPath));
            $encoded = rawurlencode($filename);

            $response->setHeader(
                'Content-Disposition',
                "attachment; filename=\"{$filename}\"; filename*=UTF-8''{$encoded}"
            );
        }

        return $response;
    }

    private static function safeDownloadName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F"]+/u', '_', $name) ?: 'download';

        return trim($name) !== '' ? trim($name) : 'download';
    }

    private static function toUtf8(string $value): string
    {
        if (! function_exists('mb_detect_encoding') || ! function_exists('mb_convert_encoding')) {
            return $value;
        }

        $encoding = mb_detect_encoding($value, mb_detect_order(), true) ?: 'UTF-8';

        return mb_convert_encoding($value, 'UTF-8', $encoding);
    }

    private static function mimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'css'   => 'text/css; charset=UTF-8',
            'js',
            'mjs'   => 'text/javascript; charset=UTF-8',
            'html'  => 'text/html; charset=UTF-8',
            'xml'   => 'application/xml; charset=UTF-8',
            'json'  => 'application/json; charset=UTF-8',
            'txt'   => 'text/plain; charset=UTF-8',
            'svg'   => 'image/svg+xml',
            'webp'  => 'image/webp',
            'avif'  => 'image/avif',
            'png'   => 'image/png',
            'jpg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'ico'   => 'image/x-icon',
            'pdf'   => 'application/pdf',
            'mp4'   => 'video/mp4',
            'webm'  => 'video/webm',
            'ogg'   => 'video/ogg',
            default => self::detectMimeType($path),
        };
    }

    private static function detectMimeType(string $path): string
    {
        if (! class_exists(\finfo::class)) {
            return 'application/octet-stream';
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);

        return is_string($mime) && $mime !== ''
            ? $mime
            : 'application/octet-stream';
    }
}
