<?php
namespace App\Controllers\Admin;

use App\Models\HomeJumbotronSlide;
use Whis\App;
use Whis\Http\Controller;
use Whis\Http\Request;

class Jumbotron extends Controller
{
    public function index()
    {
        if (isGuest()) {
            return redirect('/login');
        }

        $slides = HomeJumbotronSlide::byPage('home');

        return view('pages/admin/jumbotron', 'Jumbotron', [
            'user'   => auth(),
            'slides' => $slides,
            'stats'  => $this->makeStats($slides),
        ], 'layouts/admin/layout');
    }

    public function create()
    {
        if (isGuest()) {
            return redirect('/login');
        }

        return view('pages/admin/jumbotron-form', 'Nuevo slide', [
            'user'  => auth(),
            'mode'  => 'create',
            'slide' => [
                'page'              => 'home',
                'status'            => HomeJumbotronSlide::STATUS_DRAFT,
                'sort_order'        => HomeJumbotronSlide::nextSortOrder('home'),
                'background_type'   => 'image',
                'media_mode'        => 'background',
                'content_position'  => 'center',
                'overlay_enabled'   => 1,
                'is_critical'       => 0,
                'video_preload'     => 'none',
                'video_muted'       => 1,
                'video_playsinline' => 1,
                'slide_class'       => 'splide__slide--site-hero',
                'content_class'     => 'content content--site-hero',
                'button_class'      => 'button__primary button__primary--light',
            ],
        ], 'layouts/admin/layout');
    }

    public function edit(int $id)
    {
        if (isGuest()) {
            return redirect('/login');
        }

        $slide = HomeJumbotronSlide::findArray($id);

        if (! $slide) {
            return redirect('/admin/jumbotron');
        }

        return view('pages/admin/jumbotron-form', 'Editar slide', [
            'user'  => auth(),
            'mode'  => 'edit',
            'slide' => $slide,
        ], 'layouts/admin/layout');
    }

    public function store(Request $request)
    {
        if (isGuest()) {
            return $this->jsonError('No autorizado.', 401);
        }

        $payload = $this->payload($request);
        $errors  = array_merge(
            $this->validatePayload($payload),
            $this->validateUploadedFiles($request, [])
        );

        if (! empty($errors)) {
            return $this->jsonError('Revisa los campos del formulario.', $errors, 422);
        }

        $payload['slug']       = $this->makeUniqueSlug($payload['slug'] ?: $payload['title']);
        $payload['created_by'] = $this->userId(auth());
        $payload['updated_by'] = $this->userId(auth());

        $storedFiles = [];

        try {
            $payload     = $this->applyUploadedFiles($request, $payload);
            $storedFiles = $this->slideStoredFiles($payload);

            unset($payload['media_choice']);

            HomeJumbotronSlide::create($payload);

            return $this->jsonSuccess('Slide creado correctamente.', [
                'redirect' => '/admin/jumbotron',
            ]);
        } catch (\Throwable $th) {
            /*
         * Si se subieron archivos pero falló el INSERT,
         * borramos esos archivos para evitar basura.
         */
            $this->removeStoredFiles($storedFiles);

            return $this->jsonError('No se pudo crear el slide.', [
                'title' => 'Revisa que el título o slug no estén duplicados.',
            ], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        if (isGuest()) {
            return $this->jsonError('No autorizado.', 401);
        }

        $current = HomeJumbotronSlide::findArray($id);

        if (! $current) {
            return $this->jsonError('El slide no existe.', [], 404);
        }

        $payload = $this->payload($request, $current);

        $errors = array_merge(
            $this->validatePayload($payload),
            $this->validateUploadedFiles($request, $current)
        );

        if (! empty($errors)) {
            return $this->jsonError('Revisa los campos del formulario.', $errors, 422);
        }

        $payload['updated_by'] = $this->userId(auth());

        if (empty($payload['slug'])) {
            $payload['slug'] = $current['slug'] ?? $this->makeUniqueSlug($payload['title']);
        } else {
            $payload['slug'] = $this->slugify($payload['slug']);
        }

        $oldFiles = $this->slideStoredFiles($current);
        $newFiles = [];

        try {
            $payload  = $this->applyUploadedFiles($request, $payload, $current);
            $newFiles = $this->slideStoredFiles($payload);

            unset($payload['media_choice']);

            HomeJumbotronSlide::updateById($id, $payload);

            /*
         * Si el UPDATE fue correcto, borramos los archivos antiguos
         * que ya no estén referenciados por el slide.
         */
            $this->removeReplacedStoredFiles($oldFiles, $newFiles);

            return $this->jsonSuccess('Slide actualizado correctamente.', [
                'redirect' => '/admin/jumbotron',
            ]);
        } catch (\Throwable $th) {
            /*
         * Si se subió un archivo nuevo pero falló el UPDATE,
         * borramos el archivo nuevo para no dejar basura.
         */
            $this->removeReplacedStoredFiles($newFiles, $oldFiles);

            return $this->jsonError('No se pudo actualizar el slide.', [
                'title' => 'Revisa que los datos sean válidos.',
            ], 500);
        }
    }

    public function delete(int $id)
    {
        if (isGuest()) {
            return redirect('/login');
        }

        $slide = HomeJumbotronSlide::findArray($id);

        if (! $slide) {
            return redirect('/admin/jumbotron');
        }

        $files = $this->slideStoredFiles($slide);

        try {
            HomeJumbotronSlide::deleteById($id);

            /*
         * Ya eliminado de BD, borramos los archivos físicos.
         */
            $this->removeStoredFiles($files);
        } catch (\Throwable $th) {
            return redirect('/admin/jumbotron');
        }

        return redirect('/admin/jumbotron');
    }

    public function destroy(Request $request, int $id)
    {
        if (isGuest()) {
            return $this->jsonError('No autorizado.', 401);
        }

        $slide = HomeJumbotronSlide::findArray($id);

        if (! $slide) {
            return $this->jsonError('El slide no existe.', [], 404);
        }

        $files = $this->slideStoredFiles($slide);

        try {
            HomeJumbotronSlide::deleteById($id);

            /*
         * Ya eliminado de BD, borramos los archivos físicos.
         */
            $this->removeStoredFiles($files);

            return $this->jsonSuccess('Slide eliminado correctamente.', [
                'redirect' => '/admin/jumbotron',
            ]);
        } catch (\Throwable $th) {
            return $this->jsonError('No se pudo eliminar el slide.', [], 500);
        }
    }

    private function validateUploadedFiles(Request $request, array $current = []): array
    {
        $errors = [];

        $desktopFile = $this->uploadedFile($request, 'desktop_media_file');
        $mobileFile  = $this->uploadedFile($request, 'mobile_media_file');
        $posterFile  = $this->uploadedFile($request, 'video_poster_file');

        $desktopType = $desktopFile ? $this->mediaTypeOf($desktopFile) : null;
        $mobileType  = $mobileFile ? $this->mediaTypeOf($mobileFile) : null;
        $currentType = $this->currentMediaType($current);

        if ($desktopFile) {
            $error = $this->validateMediaFile($desktopFile, 'El archivo principal');

            if ($error !== null) {
                $errors['desktop_media_file'] = $error;
            }
        }

        if ($mobileFile) {
            $error = $this->validateMediaFile($mobileFile, 'El archivo móvil');

            if ($error !== null) {
                $errors['mobile_media_file'] = $error;
            }
        }

        if ($desktopType !== null && $mobileType !== null && $desktopType !== $mobileType) {
            $errors['mobile_media_file'] = 'El archivo móvil debe ser del mismo tipo que el archivo principal.';
        }

        if ($desktopType === null && $mobileType !== null && $currentType !== null && $mobileType !== $currentType) {
            $errors['mobile_media_file'] = 'El archivo móvil debe ser del mismo tipo que el archivo principal actual.';
        }

        if ($posterFile) {
            $error = $this->validateImageFile($posterFile, 'La miniatura');

            if ($error !== null) {
                $errors['video_poster_file'] = $error;
            }
        }

        return $errors;
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

    private function applyUploadedFiles(
        Request $request,
        array $payload,
        array $current = []
    ): array {
        $desktopFile = $this->uploadedFile($request, 'desktop_media_file');
        $mobileFile  = $this->uploadedFile($request, 'mobile_media_file');
        $posterFile  = $this->uploadedFile($request, 'video_poster_file');

        if (($payload['media_choice'] ?? 'media') === 'none') {
            $payload['background_type'] = 'none';
            $payload['media_mode']      = 'none';

            $payload['background_url']        = null;
            $payload['background_mobile_url'] = null;
            $payload['video_url']             = null;
            $payload['video_mobile_url']      = null;
            $payload['video_poster_url']      = null;

            return $payload;
        }

        if ($desktopFile) {
            $desktopType = $this->mediaTypeOf($desktopFile);

            if ($desktopType === 'image') {
                $payload['background_url'] = $desktopFile->store(
                    'jumbotron/images',
                    false,
                    'storage/uploads',
                    true
                );

                /*
             * Si el archivo principal cambia a imagen,
             * limpiamos cualquier video anterior.
             */
                $payload['video_url']        = null;
                $payload['video_mobile_url'] = null;
                $payload['video_poster_url'] = null;

                $payload['background_type'] = 'image';
                $payload['media_mode']      = 'background';
            }

            if ($desktopType === 'video') {
                $payload['video_url'] = $desktopFile->store(
                    'jumbotron/videos',
                    false,
                    'storage/uploads',
                    true
                );

                /*
             * Si el archivo principal cambia a video,
             * limpiamos cualquier imagen anterior.
             */
                $payload['background_url']        = null;
                $payload['background_mobile_url'] = null;

                $payload['background_type'] = 'video';
                $payload['media_mode']      = 'background';

                $payload['video_autoplay']    = 1;
                $payload['video_muted']       = 1;
                $payload['video_loop']        = 1;
                $payload['video_playsinline'] = 1;
                $payload['video_preload']     = 'none';
            }
        }

        if ($mobileFile) {
            $mobileType = $this->mediaTypeOf($mobileFile);

            if ($mobileType === 'image') {
                $payload['background_mobile_url'] = $mobileFile->store(
                    'jumbotron/images',
                    false,
                    'storage/uploads',
                    true
                );

                $payload['video_mobile_url'] = null;
            }

            if ($mobileType === 'video') {
                $payload['video_mobile_url'] = $mobileFile->store(
                    'jumbotron/videos',
                    false,
                    'storage/uploads',
                    true
                );

                $payload['background_mobile_url'] = null;

                $payload['video_autoplay']    = 1;
                $payload['video_muted']       = 1;
                $payload['video_loop']        = 1;
                $payload['video_playsinline'] = 1;
                $payload['video_preload']     = 'none';
            }
        }

        if ($posterFile) {
            $payload['video_poster_url'] = $posterFile->store(
                'jumbotron/images',
                false,
                'storage/uploads',
                true
            );
        }

        if (! empty($payload['video_url']) || ! empty($payload['video_mobile_url'])) {
            $payload['background_type'] = 'video';
            $payload['media_mode']      = 'background';

            $payload['background_url']        = null;
            $payload['background_mobile_url'] = null;

            $payload['video_autoplay']    = 1;
            $payload['video_muted']       = 1;
            $payload['video_loop']        = 1;
            $payload['video_playsinline'] = 1;
            $payload['video_preload']     = 'none';

            return $payload;
        }

        if (! empty($payload['background_url']) || ! empty($payload['background_mobile_url'])) {
            $payload['background_type'] = 'image';
            $payload['media_mode']      = 'background';

            $payload['video_url']        = null;
            $payload['video_mobile_url'] = null;
            $payload['video_poster_url'] = null;

            return $payload;
        }

        $payload['background_type'] = 'none';
        $payload['media_mode']      = 'none';

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

    private function payload(Request $request, array $current = []): array
    {
        $input = $request->data();

        $buttonTargetChoice = $this->str(
            $input,
            'button_target_choice',
            (($current['button_target'] ?? '') === '_blank') ? 'new' : 'same'
        );

        $buttonTarget = $buttonTargetChoice === 'new' ? '_blank' : '_self';
        $buttonRel    = $buttonTarget === '_blank' ? 'noopener noreferrer' : null;

        $backgroundUrl       = $this->nullable($input, 'background_url', $current['background_url'] ?? null);
        $backgroundMobileUrl = $this->nullable($input, 'background_mobile_url', $current['background_mobile_url'] ?? null);
        $videoUrl            = $this->nullable($input, 'video_url', $current['video_url'] ?? null);
        $videoMobileUrl      = $this->nullable($input, 'video_mobile_url', $current['video_mobile_url'] ?? null);
        $videoPosterUrl      = $this->nullable($input, 'video_poster_url', $current['video_poster_url'] ?? null);

        $mediaChoice = $this->str($input, 'media_choice', $this->currentMediaType($current) ? 'media' : 'none');

        $currentType = $this->currentMediaType([
            'background_url'        => $backgroundUrl,
            'background_mobile_url' => $backgroundMobileUrl,
            'video_url'             => $videoUrl,
            'video_mobile_url'      => $videoMobileUrl,
        ]);

        $backgroundType = $mediaChoice === 'none'
            ? 'none'
            : ($currentType ?? 'none');

        $mediaMode = $backgroundType === 'none'
            ? 'none'
            : 'background';

        return [
            'page'                  => 'home',
            'slug'                  => $this->str($input, 'slug', $current['slug'] ?? ''),
            'status'                => $this->str($input, 'status', $current['status'] ?? HomeJumbotronSlide::STATUS_DRAFT),
            'sort_order'            => max(0, (int) $this->str($input, 'sort_order', $current['sort_order'] ?? HomeJumbotronSlide::nextSortOrder('home'))),

            // Valores fijos. Ya no los toca el usuario.
            'slide_class'           => 'splide__slide--site-hero',
            'content_class'         => 'content content--site-hero',
            'button_class'          => 'button__primary button__primary--light',
            'content_position'      => 'center',
            'overlay_enabled'       => 1,
            'overlay_variant'       => null,
            'is_critical'           => 0,

            'media_choice'          => $mediaChoice,

            'media_mode'            => $mediaMode,
            'background_type'       => $backgroundType,
            'background_url'        => $backgroundUrl,
            'background_mobile_url' => $backgroundMobileUrl,

            // Se conservan por compatibilidad con la tabla, pero ya no se editan desde este form.
            'video_url'             => $videoUrl,
            'video_mobile_url'      => $videoMobileUrl,
            'video_poster_url'      => $videoPosterUrl,
            'video_aria_label'      => $current['video_aria_label'] ?? null,
            'video_preload'         => $current['video_preload'] ?? 'none',
            'video_controls'        => (int) ($current['video_controls'] ?? 0),
            'video_autoplay'        => (int) ($current['video_autoplay'] ?? 0),
            'video_muted'           => (int) ($current['video_muted'] ?? 1),
            'video_loop'            => (int) ($current['video_loop'] ?? 0),
            'video_playsinline'     => (int) ($current['video_playsinline'] ?? 1),

            'eyebrow'               => $this->nullable($input, 'eyebrow', $current['eyebrow'] ?? null),
            'title'                 => $this->str($input, 'title', $current['title'] ?? ''),
            'subtitle'              => $this->nullable($input, 'subtitle', $current['subtitle'] ?? null),
            'body'                  => $this->nullable($input, 'body', $current['body'] ?? null),

            'button_label'          => $this->nullable($input, 'button_label', $current['button_label'] ?? null),
            'button_url'            => $this->nullable($input, 'button_url', $current['button_url'] ?? null),
            'button_title'          => null,
            'button_aria_label'     => null,
            'button_target'         => $buttonTarget,
            'button_rel'            => $buttonRel,

            'custom_style'          => null,
            'extra_attributes'      => null,

            'starts_at'             => $this->dateTime($input, 'starts_at'),
            'ends_at'               => $this->dateTime($input, 'ends_at'),
        ];
    }

    private function validatePayload(array $payload): array
    {
        $errors = [];

        if (trim((string) ($payload['title'] ?? '')) === '') {
            $errors['title'] = 'El título es obligatorio.';
        }

        if (mb_strlen((string) ($payload['title'] ?? '')) > 255) {
            $errors['title'] = 'El título no debe exceder 255 caracteres.';
        }

        if (! in_array($payload['status'], [
            HomeJumbotronSlide::STATUS_DRAFT,
            HomeJumbotronSlide::STATUS_PUBLISHED,
            HomeJumbotronSlide::STATUS_HIDDEN,
        ], true)) {
            $errors['status'] = 'Selecciona un estado válido.';
        }

        if (! empty($payload['starts_at']) && ! empty($payload['ends_at'])) {
            if (strtotime($payload['ends_at']) <= strtotime($payload['starts_at'])) {
                $errors['ends_at'] = 'La fecha de expiración debe ser posterior a la fecha de inicio.';
            }
        }

        return $errors;
    }

    private function makeStats(array $slides): array
    {
        $published = 0;
        $expired   = 0;
        $draft     = 0;
        $hidden    = 0;

        foreach ($slides as $slide) {
            $status = $slide['status'] ?? 'draft';

            if ($status === 'published' && HomeJumbotronSlide::isVisibleNow($slide)) {
                $published++;
            }

            if (HomeJumbotronSlide::isExpired($slide)) {
                $expired++;
            }

            if ($status === 'draft') {
                $draft++;
            }

            if ($status === 'hidden') {
                $hidden++;
            }
        }

        return [
            'total'               => count($slides),
            'published'           => $published,
            'expired'             => $expired,
            'draft'               => $draft,
            'hidden'              => $hidden,

            /*
             * También dejo esta key porque tu dashboard actual espera:
             * $stats['jumbotron_published']
             */
            'jumbotron_published' => $published,
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

    private function dateTime(array $input, string $key): ?string
    {
        $value = $this->str($input, $key);

        if ($value === '') {
            return null;
        }

        $value = str_replace('T', ' ', $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
            $value .= ':00';
        }

        $timestamp = strtotime($value);

        if (! $timestamp) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
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
            return 'slide';
        }

        $converted = iconv('UTF-8', 'ASCII//TRANSLIT', $text);

        if ($converted !== false) {
            $text = $converted;
        }

        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim((string) $text, '-');

        return $text ?: 'slide';
    }
    private function validateMediaFile(mixed $file, string $label): ?string
    {
        $type = $this->mediaTypeOf($file);

        if ($type === 'image') {
            return $this->validateImageFile($file, $label);
        }

        if ($type === 'video') {
            return $this->validateVideoFile($file, $label);
        }

        return $label . ' debe ser una imagen PNG, JPG, JPEG, WEBP o un video MP4/WEBM.';
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

    private function validateVideoFile(mixed $file, string $label): ?string
    {
        return $this->validateSingleFile(
            $file,
            ['mp4', 'webm'],
            ['video/mp4', 'video/webm'],
            35 * 1024 * 1024,
            $label
        );
    }

    private function mediaTypeOf(mixed $file): ?string
    {
        $mime = method_exists($file, 'type')
            ? strtolower((string) $file->type())
            : '';

        $extension = method_exists($file, 'extension')
            ? strtolower((string) $file->extension(true))
            : '';

        if (
            str_starts_with($mime, 'image/')
            || in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)
        ) {
            return 'image';
        }

        if (
            str_starts_with($mime, 'video/')
            || in_array($extension, ['mp4', 'webm'], true)
        ) {
            return 'video';
        }

        return null;
    }

    private function currentMediaType(array $current): ?string
    {
        if (! empty($current['video_url']) || ! empty($current['video_mobile_url'])) {
            return 'video';
        }

        if (! empty($current['background_url']) || ! empty($current['background_mobile_url'])) {
            return 'image';
        }

        return null;
    }
    private function slideStoredFiles(array $slide): array
    {
        $fields = [
            'background_url',
            'background_mobile_url',
            'video_url',
            'video_mobile_url',
            'video_poster_url',
        ];

        $files = [];

        foreach ($fields as $field) {
            $path = $this->storedUploadPathFromUrl($slide[$field] ?? null);

            if ($path !== null) {
                $files[$path] = $path;
            }
        }

        return array_values($files);
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
        $path = trim(str_replace('\\', '/', (string) $path));

        if ($path === '') {
            return;
        }

        try {
            if (is_file($path)) {
                @unlink($path);
                return;
            }

            error_log('[Jumbotron cleanup] No existe el archivo: ' . $path);
        } catch (\Throwable $th) {
            error_log('[Jumbotron cleanup] Error eliminando archivo: ' . $th->getMessage());
        }
    }

    private function storedUploadPathFromUrl(mixed $url): ?string
    {
        $value = trim((string) ($url ?? ''));

        if ($value === '') {
            return null;
        }

        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = rawurldecode($value);
        $value = str_replace('\\', '/', $value);

        /*
     * Puede venir como:
     * - http://localhost/storage/uploads/jumbotron/images/archivo.webp
     * - /storage/uploads/jumbotron/images/archivo.webp
     * - storage/uploads/jumbotron/images/archivo.webp
     * - jumbotron/images/archivo.webp
     * - jumbotron/videos/archivo.mp4
     */
        $urlPath = parse_url($value, PHP_URL_PATH);

        if (! is_string($urlPath) || trim($urlPath) === '') {
            $urlPath = $value;
        }

        $urlPath = trim(str_replace('\\', '/', $urlPath), '/');

        $needle   = 'storage/uploads/';
        $position = strpos($urlPath, $needle);

        if ($position !== false) {
            $relative = substr($urlPath, $position + strlen($needle));
        } else {
            $relative = $urlPath;
        }

        $relative = trim(str_replace('\\', '/', $relative), '/');

        if ($relative === '') {
            return null;
        }

        if (str_contains($relative, '..') || str_contains($relative, "\0")) {
            return null;
        }

        /*
     * Protección: este CRUD solo borra archivos del jumbotron.
     */
        if (
            ! str_starts_with($relative, 'jumbotron/images/')
            && ! str_starts_with($relative, 'jumbotron/videos/')
        ) {
            return null;
        }

        $baseDirectory =
            rtrim(str_replace('\\', '/', App::$root), '/')
            . '/storage/uploads';

        $baseReal = realpath($baseDirectory);

        if ($baseReal === false) {
            return null;
        }

        $baseReal = rtrim(str_replace('\\', '/', $baseReal), '/');

        $candidate = $baseReal . '/' . $relative;
        $candidate = str_replace('\\', '/', $candidate);

        $candidateDirectory     = dirname($candidate);
        $candidateDirectoryReal = realpath($candidateDirectory);

        if ($candidateDirectoryReal === false) {
            return null;
        }

        $candidateDirectoryReal = rtrim(str_replace('\\', '/', $candidateDirectoryReal), '/');

        if (
            $candidateDirectoryReal !== $baseReal
            && ! str_starts_with($candidateDirectoryReal, $baseReal . '/')
        ) {
            return null;
        }

        return $candidate;
    }
}
