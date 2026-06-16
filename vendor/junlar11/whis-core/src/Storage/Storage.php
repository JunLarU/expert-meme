<?php
namespace Whis\Storage;

use Whis\Routing\Route;
use Whis\Storage\Drivers\FileStorageDriver;

class Storage
{
    /**
     * Put file in the storage directory.
     *
     * @param string $path
     * @param mixed $content
     * @return string URL of the file.
     */
    public static function put(
        string $path,
        mixed $content,
        bool $returnPath = false,
        ?string $alternativeDirectory = null,
        ?string $customUrl = null
    ): string {
        return app(FileStorageDriver::class)->put(
            $path,
            $content,
            $returnPath,
            $alternativeDirectory,
            $customUrl
        );
    }

    public static function remove(string $path): bool
    {
        return app(FileStorageDriver::class)->remove($path);
    }

    public static function download(
        string $filename,
        bool $asset = false,
        ?string $alternativeDirectory = null,
        string $folder = ""
    ) {
        $filename = trim($folder . "/" . $filename, "/");

        if (in_array($folder, config("storage.assets")) || $asset) {
            return app(FileStorageDriver::class)->downloadFile(
                $filename,
                true,
                $alternativeDirectory
            );
        }

        if ($folder === "storage") {
            $filename = str_replace("storage/", "", $filename);
        }

        return app(FileStorageDriver::class)->downloadFile(
            $filename,
            false,
            $alternativeDirectory
        );
    }

    public static function get(
        string $filename,
        bool $asset = false,
        ?string $alternativeDirectory = null,
        string $folder = ""
    ) {
        $filename = trim($folder . "/" . $filename, "/");

        if ($asset) {
            return app(FileStorageDriver::class)->getFile(
                $filename,
                true,
                $alternativeDirectory
            );
        }

        if ($folder === "storage") {
            $filename = str_replace("storage/", "", $filename);
        }

        return app(FileStorageDriver::class)->getFile(
            $filename,
            false,
            $alternativeDirectory
        );
    }

    public static function Routes()
    {
        /**
         * Rutas SEO directas
         */
        Route::get('/robots.txt', function () {
            return self::get('robots.txt', true);
        });

        Route::get('/sitemap.xml', function () {
            return self::get('sitemap.xml', true);
        });

        /**
         * Ruta específica para archivos privados/semipúblicos guardados en:
         *
         * storage/uploads/...
         *
         * Ejemplo:
         * /storage/uploads/profile_pictures/archivo.png
         */
        Route::get('/storage/uploads/{filename:.*}', function (string $filename) {
            if ($filename === '') {
                return;
            }

            return self::get(
                $filename,
                false,
                "storage/uploads"
            );
        });

        Route::get('/download/storage/uploads/{filename:.*}', function (string $filename) {
            if ($filename === '') {
                return;
            }

            return self::download(
                $filename,
                false,
                "storage/uploads"
            );
        });

        /**
         * Ruta genérica de descarga.
         */
        Route::get('/download/{folder}/{filename:.*}', function (string $folder, string $filename) {
            if ($folder === '' || $filename === '') {
                return;
            }

            $asset = in_array($folder, config("storage.assets"));

            return self::download(
                $filename,
                $asset,
                null,
                $folder
            );
        });

        /**
         * Ruta genérica de archivos.
         */
        Route::get('/{folder}/{filename:.*}', function (string $folder, string $filename) {
            if ($folder === '' || $filename === '') {
                return;
            }

            $asset = in_array($folder, config("storage.assets"));

            return self::get(
                $filename,
                $asset,
                null,
                $folder
            );
        });
    }
}
