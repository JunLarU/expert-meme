<?php
namespace App\Controllers\Admin;

use App\Models\User;
use App\Models\ValuationUnit;
use Whis\App;
use Whis\Http\Controller;
use Whis\Http\Request;
use Whis\Storage\Storage;

class ValuationUnits extends Controller
{
    public function index()
    {
        if ($response = $this->denyPageUnlessAdmin()) {
            return $response;
        }

        $units = ValuationUnit::ordered();

        return view('pages/admin/valuation-units', 'Unidades de valuación', [
            'user'  => auth(),
            'units' => $units,
            'stats' => $this->makeStats($units),
        ], 'layouts/admin/layout');
    }

    public function create()
    {
        if ($response = $this->denyPageUnlessAdmin()) {
            return $response;
        }

        return view('pages/admin/valuation-unit-form', 'Nueva unidad de valuación', [
            'user' => auth(),
            'mode' => 'create',
            'unit' => [
                'is_primary' => 0,
                'is_active'  => 1,
                'sort_order' => ValuationUnit::nextSortOrder(),
            ],
        ], 'layouts/admin/layout');
    }

    public function edit(int $id)
    {
        if ($response = $this->denyPageUnlessAdmin()) {
            return $response;
        }

        $unit = ValuationUnit::findArray($id);

        if (! $unit) {
            return redirect('/admin/valuacion/unidades');
        }

        return view('pages/admin/valuation-unit-form', 'Editar unidad de valuación', [
            'user' => auth(),
            'mode' => 'edit',
            'unit' => $unit,
        ], 'layouts/admin/layout');
    }

    public function store(Request $request)
    {
        if ($response = $this->denyJsonUnlessAdmin()) {
            return $response;
        }

        $payload = $this->payload($request);
        $errors = array_merge(
            $this->validatePayload($payload),
            $this->validateUploadedFiles($request)
        );

        if (! empty($errors)) {
            return $this->jsonError('Revisa los campos del formulario.', $errors, 422);
        }

        $payload['slug']       = $this->makeUniqueSlug($payload['slug'] ?: $payload['name'], 'unidad-valuacion');
        $payload['created_by'] = $this->userId(auth());
        $payload['updated_by'] = $this->userId(auth());

        $storedFiles = [];

        try {
            $payload = $this->applyUploadedFile($request, $payload);
            $storedFiles = $this->storedFiles($payload);

            unset($payload['remove_logo']);

            ValuationUnit::create($payload);

            return $this->jsonSuccess('Unidad de valuación creada correctamente.', [
                'redirect' => '/admin/valuacion/unidades',
            ]);
        } catch (\Throwable $th) {
            $this->removeStoredFiles($storedFiles);

            return $this->jsonError('No se pudo crear la unidad de valuación.', [
                'name' => 'Revisa que el nombre o slug no estén duplicados.',
            ], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        if ($response = $this->denyJsonUnlessAdmin()) {
            return $response;
        }

        $current = ValuationUnit::findArray($id);

        if (! $current) {
            return $this->jsonError('La unidad de valuación no existe.', [], 404);
        }

        $payload = $this->payload($request, $current);
        $errors = array_merge(
            $this->validatePayload($payload),
            $this->validateUploadedFiles($request)
        );

        if (! empty($errors)) {
            return $this->jsonError('Revisa los campos del formulario.', $errors, 422);
        }

        $payload['updated_by'] = $this->userId(auth());

        if (empty($payload['slug'])) {
            $payload['slug'] = $current['slug'] ?? $this->makeUniqueSlug($payload['name'], 'unidad-valuacion');
        } else {
            $payload['slug'] = $this->slugify($payload['slug'], 'unidad-valuacion');
        }

        $oldFiles = $this->storedFiles($current);
        $newFiles = [];

        try {
            $payload = $this->applyUploadedFile($request, $payload, $current);
            $newFiles = $this->storedFiles($payload);

            unset($payload['remove_logo']);

            ValuationUnit::updateById($id, $payload);

            $this->removeReplacedStoredFiles($oldFiles, $newFiles);

            return $this->jsonSuccess('Unidad de valuación actualizada correctamente.', [
                'redirect' => '/admin/valuacion/unidades',
            ]);
        } catch (\Throwable $th) {
            $this->removeReplacedStoredFiles($newFiles, $oldFiles);

            return $this->jsonError('No se pudo actualizar la unidad de valuación.', [
                'name' => 'Revisa que los datos sean válidos.',
            ], 500);
        }
    }

    public function delete(int $id)
    {
        if ($response = $this->denyPageUnlessAdmin()) {
            return $response;
        }

        $unit = ValuationUnit::findArray($id);

        if (! $unit) {
            return redirect('/admin/valuacion/unidades');
        }

        $files = $this->storedFiles($unit);

        try {
            ValuationUnit::deleteById($id);
            $this->removeStoredFiles($files);
        } catch (\Throwable $th) {
            return redirect('/admin/valuacion/unidades');
        }

        return redirect('/admin/valuacion/unidades');
    }

    public function destroy(Request $request, int $id)
    {
        if ($response = $this->denyJsonUnlessAdmin()) {
            return $response;
        }

        $unit = ValuationUnit::findArray($id);

        if (! $unit) {
            return $this->jsonError('La unidad de valuación no existe.', [], 404);
        }

        $files = $this->storedFiles($unit);

        try {
            ValuationUnit::deleteById($id);
            $this->removeStoredFiles($files);

            return $this->jsonSuccess('Unidad de valuación eliminada correctamente.', [
                'redirect' => '/admin/valuacion/unidades',
            ]);
        } catch (\Throwable $th) {
            return $this->jsonError('No se pudo eliminar la unidad de valuación.', [], 500);
        }
    }

    private function payload(Request $request, array $current = []): array
    {
        $input = $request->data();

        return [
            'name'        => $this->str($input, 'name', $current['name'] ?? ''),
            'slug'        => $this->str($input, 'slug', $current['slug'] ?? ''),
            'short_name'  => $this->nullable($input, 'short_name', $current['short_name'] ?? null),
            'role_label'  => $this->nullable($input, 'role_label', $current['role_label'] ?? null),
            'url'         => $this->nullable($input, 'url', $current['url'] ?? null),
            'logo_url'    => $this->nullable($input, 'logo_url', $current['logo_url'] ?? null),
            'logo_alt'    => $this->nullable($input, 'logo_alt', $current['logo_alt'] ?? null),
            'description' => $this->nullable($input, 'description', $current['description'] ?? null),
            'is_primary'  => $this->bool($input, 'is_primary', (bool) ($current['is_primary'] ?? false)),
            'is_active'   => $this->bool($input, 'is_active', (bool) ($current['is_active'] ?? true)),
            'sort_order'  => max(0, (int) $this->str($input, 'sort_order', $current['sort_order'] ?? ValuationUnit::nextSortOrder())),
            'remove_logo' => $this->bool($input, 'remove_logo', false),
        ];
    }

    private function validatePayload(array $payload): array
    {
        $errors = [];

        if (trim((string) ($payload['name'] ?? '')) === '') {
            $errors['name'] = 'El nombre de la unidad es obligatorio.';
        }

        if (mb_strlen((string) ($payload['name'] ?? '')) > 255) {
            $errors['name'] = 'El nombre no debe exceder 255 caracteres.';
        }

        if (mb_strlen((string) ($payload['slug'] ?? '')) > 180) {
            $errors['slug'] = 'El slug no debe exceder 180 caracteres.';
        }

        if (mb_strlen((string) ($payload['short_name'] ?? '')) > 120) {
            $errors['short_name'] = 'El nombre corto no debe exceder 120 caracteres.';
        }

        if (mb_strlen((string) ($payload['role_label'] ?? '')) > 160) {
            $errors['role_label'] = 'La etiqueta no debe exceder 160 caracteres.';
        }

        if (! empty($payload['url']) && ! filter_var($payload['url'], FILTER_VALIDATE_URL)) {
            $errors['url'] = 'Captura una URL válida, incluyendo http:// o https://.';
        }

        return $errors;
    }

    private function validateUploadedFiles(Request $request): array
    {
        $errors = [];
        $logoFile = $this->uploadedFile($request, 'logo_file');

        if ($logoFile) {
            $error = $this->validateImageFile($logoFile, 'El logo');

            if ($error !== null) {
                $errors['logo_file'] = $error;
            }
        }

        return $errors;
    }

    private function applyUploadedFile(Request $request, array $payload, array $current = []): array
    {
        $logoFile = $this->uploadedFile($request, 'logo_file');

        if ((int) ($payload['remove_logo'] ?? 0) === 1) {
            $payload['logo_url'] = null;
        }

        if ($logoFile) {
            $payload['logo_url'] = $logoFile->store(
                'valuation/units/logos',
                false,
                'storage/uploads',
                true
            );
        }

        return $payload;
    }

    private function uploadedFile(Request $request, string $name): mixed
    {
        $file = $request->file($name);

        if (! $file) {
            return null;
        }

        if (is_array($file)) {
            foreach ($file as $item) {
                if ($item) {
                    return $item;
                }
            }

            return null;
        }

        return $file;
    }

    private function validateSingleFile(mixed $file, array $allowedExtensions, array $allowedMimeTypes, int $maxBytes, string $label): ?string
    {
        if (method_exists($file, 'hasUploadError') && $file->hasUploadError()) {
            return $label . ' no se pudo subir correctamente.';
        }

        if (method_exists($file, 'size') && $file->size() > $maxBytes) {
            return $label . ' excede el tamaño máximo permitido.';
        }

        $mime = method_exists($file, 'type') ? strtolower((string) $file->type()) : '';
        $extension = method_exists($file, 'extension') ? strtolower((string) $file->extension(true)) : '';

        $mimeAllowed = $mime !== '' && in_array($mime, $allowedMimeTypes, true);
        $extensionAllowed = $extension !== '' && in_array($extension, $allowedExtensions, true);

        if (! $mimeAllowed && ! $extensionAllowed) {
            return $label . ' tiene un tipo de archivo no permitido.';
        }

        return null;
    }

    private function validateImageFile(mixed $file, string $label): ?string
    {
        return $this->validateSingleFile(
            $file,
            ['png', 'jpg', 'jpeg', 'webp', 'svg'],
            ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'],
            4 * 1024 * 1024,
            $label
        );
    }

    private function makeStats(array $units): array
    {
        $active = 0;
        $inactive = 0;
        $primary = 0;

        foreach ($units as $unit) {
            if ((int) ($unit['is_active'] ?? 0) === 1) {
                $active++;
            } else {
                $inactive++;
            }

            if ((int) ($unit['is_primary'] ?? 0) === 1) {
                $primary++;
            }
        }

        return [
            'total' => count($units),
            'active' => $active,
            'inactive' => $inactive,
            'primary' => $primary,
        ];
    }

    private function str(array $input, string $key, mixed $default = ''): string
    {
        return trim((string) ($input[$key] ?? $default ?? ''));
    }

    private function nullable(array $input, string $key, mixed $default = null): ?string
    {
        $value = $this->str($input, $key, $default ?? '');

        return $value === '' ? null : $value;
    }

    private function bool(array $input, string $key, bool $default = false): int
    {
        if (! array_key_exists($key, $input)) {
            return $default ? 1 : 0;
        }

        return in_array($input[$key], ['1', 1, true, 'true', 'on', 'yes'], true) ? 1 : 0;
    }

    private function denyPageUnlessAdmin()
    {
        if (isGuest()) {
            return redirect('/login');
        }

        if (! $this->currentUserIsAdmin()) {
            return redirect('/admin');
        }

        return null;
    }

    private function denyJsonUnlessAdmin()
    {
        if (isGuest()) {
            return $this->jsonError('No autorizado.', 401);
        }

        if (! $this->currentUserIsAdmin()) {
            return $this->jsonError('Solo un administrador puede gestionar este módulo.', [], 403);
        }

        return null;
    }

    private function currentUserIsAdmin(): bool
    {
        return $this->currentUserRole() === User::ROLE_ADMIN;
    }

    private function currentUserRole(): string
    {
        return strtolower(trim((string) $this->currentUserValue('role', '')));
    }

    private function currentUserValue(string $key, mixed $default = null): mixed
    {
        $user = auth();

        if (! $user) {
            return $default;
        }

        if (is_array($user)) {
            return $user[$key] ?? $default;
        }

        if (is_object($user)) {
            if (method_exists($user, 'toArray')) {
                $data = $user->toArray();

                if (is_array($data) && array_key_exists($key, $data)) {
                    return $data[$key];
                }
            }

            try {
                $value = $user->{$key};

                return $value ?? $default;
            } catch (\Throwable $th) {
                return $default;
            }
        }

        return $default;
    }

    private function currentUserId(): int
    {
        return (int) $this->currentUserValue('id', 0);
    }

    private function userId(mixed $user): ?int
    {
        $id = $this->currentUserId();

        return $id > 0 ? $id : null;
    }

    private function makeUniqueSlug(string $text, string $fallback): string
    {
        return $this->slugify($text, $fallback) . '-' . date('YmdHis');
    }

    private function slugify(string $text, string $fallback): string
    {
        $text = trim($text);

        if ($text === '') {
            return $fallback;
        }

        $converted = iconv('UTF-8', 'ASCII//TRANSLIT', $text);

        if ($converted !== false) {
            $text = $converted;
        }

        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim((string) $text, '-');

        return $text ?: $fallback;
    }

    private function storedFiles(array $unit): array
    {
        $path = $this->storedUploadPathFromUrl($unit['logo_url'] ?? null, 'valuation/units/logos/');

        return $path ? [$path] : [];
    }

    private function removeReplacedStoredFiles(array $oldFiles, array $newFiles): void
    {
        if (empty($oldFiles)) {
            return;
        }

        $newFilesLookup = array_flip($newFiles);

        foreach ($oldFiles as $oldFile) {
            if (isset($newFilesLookup[$oldFile])) {
                continue;
            }

            $this->removeStoredFile($oldFile);
        }
    }

    private function removeStoredFiles(array $files): void
    {
        foreach ($files as $file) {
            $this->removeStoredFile($file);
        }
    }

    private function removeStoredFile(?string $path): void
    {
        $path = trim((string) $path);

        if ($path === '') {
            return;
        }

        try {
            Storage::remove($path);
        } catch (\Throwable $th) {
            // La limpieza de archivos no debe romper el CRUD.
        }
    }

    private function storedUploadPathFromUrl(mixed $url, string $requiredPrefix): ?string
    {
        $value = trim((string) ($url ?? ''));

        if ($value === '') {
            return null;
        }

        $value = str_replace('\\', '/', $value);
        $urlPath = parse_url($value, PHP_URL_PATH);

        if (! is_string($urlPath) || trim($urlPath) === '') {
            $urlPath = $value;
        }

        $urlPath = trim(str_replace('\\', '/', $urlPath), '/');
        $needle = 'storage/uploads/';
        $position = strpos($urlPath, $needle);

        if ($position === false) {
            return null;
        }

        $relative = substr($urlPath, $position + strlen($needle));
        $relative = trim(str_replace('\\', '/', $relative), '/');

        if ($relative === '' || str_contains($relative, '..') || str_contains($relative, "\0")) {
            return null;
        }

        if (! str_starts_with($relative, $requiredPrefix)) {
            return null;
        }

        $baseDirectory = rtrim(str_replace('\\', '/', App::$root), '/') . '/storage/uploads';
        $baseReal = realpath($baseDirectory);

        if ($baseReal === false) {
            return null;
        }

        $baseReal = rtrim(str_replace('\\', '/', $baseReal), '/');
        $candidate = $baseReal . '/' . $relative;
        $candidateDirectory = dirname($candidate);
        $directoryReal = realpath($candidateDirectory);

        if ($directoryReal === false) {
            return null;
        }

        $directoryReal = rtrim(str_replace('\\', '/', $directoryReal), '/');

        if ($directoryReal !== $baseReal && ! str_starts_with($directoryReal, $baseReal . '/')) {
            return null;
        }

        return $candidate;
    }
}
