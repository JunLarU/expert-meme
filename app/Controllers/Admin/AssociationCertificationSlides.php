<?php
namespace App\Controllers\Admin;

use App\Models\AssociationCertificationSlide;
use App\Models\User;
use Whis\App;
use Whis\Http\Controller;
use Whis\Http\Request;
use Whis\Storage\Storage;

class AssociationCertificationSlides extends Controller
{
    public function index()
    {
        if ($response = $this->denyPageUnlessAdmin()) {
            return $response;
        }

        $slides = AssociationCertificationSlide::ordered();

        return view('pages/admin/association-certification-slides', 'Asociaciones y certificaciones', [
            'user'   => auth(),
            'slides' => $slides,
            'stats'  => $this->makeStats($slides),
        ], 'layouts/admin/layout');
    }

    public function create()
    {
        if ($response = $this->denyPageUnlessAdmin()) {
            return $response;
        }

        return view('pages/admin/association-certification-slide-form', 'Nueva asociación o certificación', [
            'user'  => auth(),
            'mode'  => 'create',
            'slide' => [
                'is_active'     => 1,
                'show_in_home'  => 1,
                'show_in_about' => 1,
                'sort_order'    => AssociationCertificationSlide::nextSortOrder(),
            ],
        ], 'layouts/admin/layout');
    }

    public function edit(int $id)
    {
        if ($response = $this->denyPageUnlessAdmin()) {
            return $response;
        }

        $slide = AssociationCertificationSlide::findArray($id);

        if (! $slide) {
            return redirect('/admin/asociaciones-certificaciones');
        }

        return view('pages/admin/association-certification-slide-form', 'Editar asociación o certificación', [
            'user'  => auth(),
            'mode'  => 'edit',
            'slide' => $slide,
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

        $payload['slug']        = $this->makeUniqueSlug($payload['slug'] ?: $payload['title']);
        $payload['short_title'] = $payload['short_title'] ?: $this->shortTitleFromTitle($payload['title']);
        $payload['image_alt']   = $payload['image_alt'] ?: $payload['title'];
        $payload['created_by']  = $this->userId(auth());
        $payload['updated_by']  = $this->userId(auth());

        $storedFiles = [];

        try {
            $payload = $this->applyUploadedFile($request, $payload);
            $storedFiles = $this->slideStoredFiles($payload);

            unset($payload['remove_image']);

            AssociationCertificationSlide::create($payload);

            return $this->jsonSuccess('Asociación o certificación creada correctamente.', [
                'redirect' => '/admin/asociaciones-certificaciones',
            ]);
        } catch (\Throwable $th) {
            $this->removeStoredFiles($storedFiles);

            return $this->jsonError('No se pudo crear la asociación o certificación.', [
                'title' => 'Revisa que el nombre o slug no estén duplicados.',
            ], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        if ($response = $this->denyJsonUnlessAdmin()) {
            return $response;
        }

        $current = AssociationCertificationSlide::findArray($id);

        if (! $current) {
            return $this->jsonError('El registro no existe.', [], 404);
        }

        $payload = $this->payload($request, $current);
        $errors = array_merge(
            $this->validatePayload($payload),
            $this->validateUploadedFiles($request)
        );

        if (! empty($errors)) {
            return $this->jsonError('Revisa los campos del formulario.', $errors, 422);
        }

        $payload['updated_by']  = $this->userId(auth());
        $payload['short_title'] = $payload['short_title'] ?: $this->shortTitleFromTitle($payload['title']);
        $payload['image_alt']   = $payload['image_alt'] ?: $payload['title'];

        if (empty($payload['slug'])) {
            $payload['slug'] = $current['slug'] ?? $this->makeUniqueSlug($payload['title']);
        } else {
            $payload['slug'] = $this->slugify($payload['slug']);
        }

        $oldFiles = $this->slideStoredFiles($current);
        $newFiles = [];

        try {
            $payload = $this->applyUploadedFile($request, $payload, $current);
            $newFiles = $this->slideStoredFiles($payload);

            unset($payload['remove_image']);

            AssociationCertificationSlide::updateById($id, $payload);

            $this->removeReplacedStoredFiles($oldFiles, $newFiles);

            return $this->jsonSuccess('Asociación o certificación actualizada correctamente.', [
                'redirect' => '/admin/asociaciones-certificaciones',
            ]);
        } catch (\Throwable $th) {
            $this->removeReplacedStoredFiles($newFiles, $oldFiles);

            return $this->jsonError('No se pudo actualizar la asociación o certificación.', [
                'title' => 'Revisa que los datos sean válidos.',
            ], 500);
        }
    }

    public function delete(int $id)
    {
        if ($response = $this->denyPageUnlessAdmin()) {
            return $response;
        }

        $slide = AssociationCertificationSlide::findArray($id);

        if (! $slide) {
            return redirect('/admin/asociaciones-certificaciones');
        }

        $files = $this->slideStoredFiles($slide);

        try {
            AssociationCertificationSlide::deleteById($id);
            $this->removeStoredFiles($files);
        } catch (\Throwable $th) {
            return redirect('/admin/asociaciones-certificaciones');
        }

        return redirect('/admin/asociaciones-certificaciones');
    }

    public function destroy(Request $request, int $id)
    {
        if ($response = $this->denyJsonUnlessAdmin()) {
            return $response;
        }

        $slide = AssociationCertificationSlide::findArray($id);

        if (! $slide) {
            return $this->jsonError('El registro no existe.', [], 404);
        }

        $files = $this->slideStoredFiles($slide);

        try {
            AssociationCertificationSlide::deleteById($id);
            $this->removeStoredFiles($files);

            return $this->jsonSuccess('Asociación o certificación eliminada correctamente.', [
                'redirect' => '/admin/asociaciones-certificaciones',
            ]);
        } catch (\Throwable $th) {
            return $this->jsonError('No se pudo eliminar la asociación o certificación.', [], 500);
        }
    }

    private function payload(Request $request, array $current = []): array
    {
        $input = $request->data();

        return [
            'title'         => $this->str($input, 'title', $current['title'] ?? ''),
            'short_title'   => $this->nullable($input, 'short_title', $current['short_title'] ?? null),
            'slug'          => $this->str($input, 'slug', $current['slug'] ?? ''),
            'url'           => $this->nullable($input, 'url', $current['url'] ?? null),
            'image_url'     => $this->nullable($input, 'image_url', $current['image_url'] ?? null),
            'image_alt'     => $this->nullable($input, 'image_alt', $current['image_alt'] ?? null),
            'description'   => $this->nullable($input, 'description', $current['description'] ?? null),
            'is_active'     => $this->bool($input, 'is_active', (bool) ($current['is_active'] ?? true)),
            'show_in_home'  => $this->bool($input, 'show_in_home', (bool) ($current['show_in_home'] ?? true)),
            'show_in_about' => $this->bool($input, 'show_in_about', (bool) ($current['show_in_about'] ?? true)),
            'sort_order'    => max(0, (int) $this->str($input, 'sort_order', $current['sort_order'] ?? AssociationCertificationSlide::nextSortOrder())),
            'remove_image'  => $this->bool($input, 'remove_image', false),
        ];
    }

    private function validatePayload(array $payload): array
    {
        $errors = [];

        if (trim((string) ($payload['title'] ?? '')) === '') {
            $errors['title'] = 'El nombre es obligatorio.';
        }

        if (mb_strlen((string) ($payload['title'] ?? '')) > 255) {
            $errors['title'] = 'El nombre no debe exceder 255 caracteres.';
        }

        if (mb_strlen((string) ($payload['short_title'] ?? '')) > 80) {
            $errors['short_title'] = 'El título corto no debe exceder 80 caracteres.';
        }

        if (mb_strlen((string) ($payload['slug'] ?? '')) > 180) {
            $errors['slug'] = 'El slug no debe exceder 180 caracteres.';
        }

        if (mb_strlen((string) ($payload['image_alt'] ?? '')) > 255) {
            $errors['image_alt'] = 'El texto alternativo no debe exceder 255 caracteres.';
        }

        if (mb_strlen((string) ($payload['description'] ?? '')) > 600) {
            $errors['description'] = 'La descripción no debe exceder 600 caracteres.';
        }

        if (! empty($payload['url']) && ! filter_var($payload['url'], FILTER_VALIDATE_URL)) {
            $errors['url'] = 'Captura una URL válida, incluyendo http:// o https://.';
        }

        return $errors;
    }

    private function validateUploadedFiles(Request $request): array
    {
        $errors = [];
        $imageFile = $this->uploadedFile($request, 'image_file');

        if ($imageFile) {
            $error = $this->validateImageFile($imageFile, 'La imagen');

            if ($error !== null) {
                $errors['image_file'] = $error;
            }
        }

        return $errors;
    }

    private function applyUploadedFile(Request $request, array $payload, array $current = []): array
    {
        $imageFile = $this->uploadedFile($request, 'image_file');

        if ((int) ($payload['remove_image'] ?? 0) === 1) {
            $payload['image_url'] = null;
        }

        if ($imageFile) {
            $payload['image_url'] = $imageFile->store(
                'associations-certifications/images',
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

    private function validateSingleFile(
        mixed $file,
        array $allowedExtensions,
        array $allowedMimeTypes,
        int $maxBytes,
        string $label
    ): ?string {
        if (method_exists($file, 'hasUploadError') && $file->hasUploadError()) {
            return $label . ' no se pudo subir correctamente.';
        }

        if (method_exists($file, 'size') && $file->size() > $maxBytes) {
            return $label . ' excede el tamaño máximo permitido.';
        }

        $mime = method_exists($file, 'type')
            ? strtolower((string) $file->type())
            : '';

        $extension = method_exists($file, 'extension')
            ? strtolower((string) $file->extension(true))
            : '';

        $mimeAllowed      = $mime !== '' && in_array($mime, $allowedMimeTypes, true);
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
            ['png', 'jpg', 'jpeg', 'webp'],
            ['image/png', 'image/jpeg', 'image/webp'],
            4 * 1024 * 1024,
            $label
        );
    }

    private function makeStats(array $slides): array
    {
        $active = 0;
        $inactive = 0;
        $home = 0;
        $about = 0;

        foreach ($slides as $slide) {
            if ((int) ($slide['is_active'] ?? 0) === 1) {
                $active++;
            } else {
                $inactive++;
            }

            if ((int) ($slide['show_in_home'] ?? 0) === 1) {
                $home++;
            }

            if ((int) ($slide['show_in_about'] ?? 0) === 1) {
                $about++;
            }
        }

        return [
            'total'    => count($slides),
            'active'   => $active,
            'inactive' => $inactive,
            'home'     => $home,
            'about'    => $about,
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

    private function userId(mixed $user): ?int
    {
        return isset($user->id) ? (int) $user->id : null;
    }

    private function makeUniqueSlug(string $text): string
    {
        return $this->slugify($text) . '-' . date('YmdHis');
    }

    private function slugify(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return 'asociacion-certificacion';
        }

        $converted = iconv('UTF-8', 'ASCII//TRANSLIT', $text);

        if ($converted !== false) {
            $text = $converted;
        }

        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim((string) $text, '-');

        return $text ?: 'asociacion-certificacion';
    }

    private function shortTitleFromTitle(string $title): string
    {
        $words = preg_split('/\s+/', trim($title)) ?: [];
        $words = array_values(array_filter($words));

        if (empty($words)) {
            return 'A+C';
        }

        if (count($words) === 1) {
            return mb_substr($words[0], 0, 20);
        }

        $initials = '';

        foreach ($words as $word) {
            $clean = trim($word);

            if ($clean === '') {
                continue;
            }

            $initials .= mb_substr($clean, 0, 1);

            if (mb_strlen($initials) >= 8) {
                break;
            }
        }

        return strtoupper($initials) ?: mb_substr($title, 0, 20);
    }

    private function slideStoredFiles(array $slide): array
    {
        $path = $this->storedUploadPathFromUrl($slide['image_url'] ?? null);

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

    private function storedUploadPathFromUrl(mixed $url): ?string
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

        if (! str_starts_with($relative, 'associations-certifications/images/')) {
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
