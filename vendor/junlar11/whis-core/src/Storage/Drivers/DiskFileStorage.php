<?php
namespace Whis\Storage\Drivers;

use Whis\App;
use Whis\Storage\FileResponder;

class DiskFileStorage implements FileStorageDriver
{
    protected string $storageDirectory;

    protected string $storageUri;

    protected string $appUrl;

    protected FileResponder $fileResponder;

    public function __construct(string $storageDirectory, string $storageUri, string $appUrl)
    {
        $this->storageDirectory = $storageDirectory;
        $this->storageUri       = $storageUri;
        $this->appUrl           = $appUrl;
        $this->fileResponder    = new FileResponder($storageDirectory);
    }
    public function put(
        string $path,
        mixed $content,
        bool $returnPath = false,
        ?string $alternativeDirectory = null,
        ?string $customUrl = null
    ): string {
        $path = trim(str_replace('\\', '/', $path), '/');

        if ($alternativeDirectory !== null) {
            $baseDir = App::$root . "/" . trim(str_replace('\\', '/', $alternativeDirectory), '/');
        } else {
            $baseDir = rtrim(str_replace('\\', '/', $this->storageDirectory), '/');
        }

        $fullPath  = $baseDir . "/" . $path;
        $directory = dirname($fullPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($fullPath, $content);

        if ($returnPath) {
            return $fullPath;
        }

        if ($customUrl !== null) {
            $urlBase = trim($customUrl, '/');
            return $this->appUrl . "/" . $urlBase . "/" . basename($path);
        }

        $urlBase = $alternativeDirectory !== null
            ? trim($alternativeDirectory, '/')
            : trim($this->storageUri, '/');

        return $this->appUrl . "/" . $urlBase . "/" . $path;
    }

    public function getFile(?string $filename = null, bool $asset = false, ?string $alternativeDirectory = null)
    {
        $this->fileResponder->getFile($filename, $asset, $alternativeDirectory);
    }
    public function downloadFile(?string $filename = null, bool $asset = false, ?string $alternativeDirectory = null)
    {
        $this->fileResponder->downloadFile($filename, $asset, $alternativeDirectory);

    }
    public function remove(string $path): bool
    {
        if (file_exists($path)) {
            unlink($path);
            return true;
        } else {
            return false;
        }
    }
}
