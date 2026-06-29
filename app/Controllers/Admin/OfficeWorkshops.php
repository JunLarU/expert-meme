<?php
namespace App\Controllers\Admin;

use App\Models\OfficeWorkshop;
use App\Models\User;
use Whis\App;
use Whis\Http\Controller;
use Whis\Http\Request;
use Whis\Storage\Storage;

class OfficeWorkshops extends Controller
{
    private const MX_STATES = [
        'Aguascalientes',
        'Baja California',
        'Baja California Sur',
        'Campeche',
        'Chiapas',
        'Chihuahua',
        'Ciudad de México',
        'Coahuila',
        'Colima',
        'Durango',
        'Estado de México',
        'Guanajuato',
        'Guerrero',
        'Hidalgo',
        'Jalisco',
        'Michoacán',
        'Morelos',
        'Nayarit',
        'Nuevo León',
        'Oaxaca',
        'Puebla',
        'Querétaro',
        'Quintana Roo',
        'San Luis Potosí',
        'Sinaloa',
        'Sonora',
        'Tabasco',
        'Tamaulipas',
        'Tlaxcala',
        'Veracruz',
        'Yucatán',
        'Zacatecas',
    ];

    public function index()
    {
        if ($response = $this->denyPageUnlessAdmin()) {
            return $response;
        }

        $items = OfficeWorkshop::allItems();

        return view('pages/admin/office-workshops', 'Oficinas y talleres', [
            'user'  => auth(),
            'items' => $items,
            'stats' => $this->makeStats($items),
        ], 'layouts/admin/layout');
    }

    public function create()
    {
        if ($response = $this->denyPageUnlessAdmin()) {
            return $response;
        }

        return view('pages/admin/office-workshop-form', 'Nueva oficina o taller', [
            'user' => auth(),
            'mode' => 'create',
            'item' => [
                'type'        => OfficeWorkshop::TYPE_OFFICE,
                'status'      => OfficeWorkshop::STATUS_DRAFT,
                'country'     => 'México',
                'show_on_map' => 1,
                'sort_order'  => OfficeWorkshop::nextSortOrder(),
            ],
        ], 'layouts/admin/layout');
    }

    public function edit(int $id)
    {
        if ($response = $this->denyPageUnlessAdmin()) {
            return $response;
        }

        $item = OfficeWorkshop::findArray($id);

        if (! $item) {
            return redirect('/admin/oficinas-talleres');
        }

        return view('pages/admin/office-workshop-form', 'Editar oficina o taller', [
            'user' => auth(),
            'mode' => 'edit',
            'item' => $item,
        ], 'layouts/admin/layout');
    }

    public function store(Request $request)
    {
        if ($response = $this->denyJsonUnlessAdmin()) {
            return $response;
        }

        $payload = $this->payload($request);
        $errors  = array_merge(
            $this->validatePayload($payload),
            $this->validateUploadedFiles($request)
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
            $storedFiles = $this->storedFiles($payload);

            OfficeWorkshop::create($payload);

            return $this->jsonSuccess('Registro creado correctamente.', [
                'redirect' => '/admin/oficinas-talleres',
            ]);
        } catch (\Throwable $th) {
            $this->removeStoredFiles($storedFiles);

            error_log('[OfficeWorkshops::store] ' . (string) $th);

            $errors = [
                'title' => 'No se pudo guardar el registro. Revisa coordenadas, campos duplicados o estructura de la tabla.',
            ];

            if ($this->debugEnabled()) {
                $errors['_debug'] = $th->getMessage();
            }

            return $this->jsonError('No se pudo crear el registro.', $errors, 500);
        }
    }

    private function debugEnabled(): bool
    {
        $value = strtolower((string) (getenv('APP_DEBUG') ?: $_ENV['APP_DEBUG'] ?? ''));

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    public function update(Request $request, int $id)
    {
        if ($response = $this->denyJsonUnlessAdmin()) {
            return $response;
        }

        $current = OfficeWorkshop::findArray($id);

        if (! $current) {
            return $this->jsonError('El registro no existe.', [], 404);
        }

        $payload = $this->payload($request, $current);
        $errors  = array_merge(
            $this->validatePayload($payload),
            $this->validateUploadedFiles($request)
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

        $oldFiles = $this->storedFiles($current);
        $newFiles = [];

        try {
            $payload = $this->applyUploadedFiles($request, $payload, $current);

            OfficeWorkshop::updateById($id, $payload);

            $updated  = OfficeWorkshop::findArray($id) ?: [];
            $newFiles = $this->storedFiles($updated);

            $this->removeReplacedStoredFiles($oldFiles, $newFiles);

            return $this->jsonSuccess('Registro actualizado correctamente.', [
                'redirect' => '/admin/oficinas-talleres',
            ]);
        } catch (\Throwable $th) {
            $uploadedNewFiles = array_diff($newFiles, $oldFiles);
            $this->removeStoredFiles($uploadedNewFiles);

            return $this->jsonError('No se pudo actualizar el registro.', [
                'title' => 'Revisa que los datos sean válidos.',
            ], 500);
        }
    }

    public function delete(int $id)
    {
        if ($response = $this->denyPageUnlessAdmin()) {
            return $response;
        }

        $item = OfficeWorkshop::findArray($id);

        if (! $item) {
            return redirect('/admin/oficinas-talleres');
        }

        $files = $this->storedFiles($item);

        try {
            OfficeWorkshop::deleteById($id);
            $this->removeStoredFiles($files);
        } catch (\Throwable $th) {
            return redirect('/admin/oficinas-talleres');
        }

        return redirect('/admin/oficinas-talleres');
    }

    public function destroy(Request $request, int $id)
    {
        if ($response = $this->denyJsonUnlessAdmin()) {
            return $response;
        }

        $item = OfficeWorkshop::findArray($id);

        if (! $item) {
            return $this->jsonError('El registro no existe.', [], 404);
        }

        $files = $this->storedFiles($item);

        try {
            OfficeWorkshop::deleteById($id);
            $this->removeStoredFiles($files);

            return $this->jsonSuccess('Registro eliminado correctamente.', [
                'redirect' => '/admin/oficinas-talleres',
            ]);
        } catch (\Throwable $th) {
            return $this->jsonError('No se pudo eliminar el registro.', [], 500);
        }
    }

    private function payload(Request $request, array $current = []): array
    {
        $input = $request->data();

        $type = $this->str($input, 'type', $current['type'] ?? OfficeWorkshop::TYPE_OFFICE);
        if (! in_array($type, OfficeWorkshop::validTypes(), true)) {
            $type = OfficeWorkshop::TYPE_OFFICE;
        }

        $title       = $this->str($input, 'title', $current['title'] ?? '');
        $summary     = $this->nullable($input, 'summary', $current['summary'] ?? null);
        $description = $this->nullable($input, 'description', $current['description'] ?? null);

        $address    = $this->nullable($input, 'address', $current['address'] ?? null);
        $city       = $this->nullable($input, 'city', $current['city'] ?? null);
        $state      = $this->nullable($input, 'state', $current['state'] ?? null);
        $country    = 'México';
        $postalCode = $this->nullable($input, 'postal_code', $current['postal_code'] ?? null);

        $mapLat    = $this->floatNullable($input, 'map_lat', $current['map_lat'] ?? null);
        $mapLng    = $this->floatNullable($input, 'map_lng', $current['map_lng'] ?? null);
        $showOnMap = $this->bool($input, 'show_on_map') ? 1 : 0;

        $googleMapsUrl = $this->urlNullable($input, 'google_maps_url', $current['google_maps_url'] ?? null);

        if ($googleMapsUrl === null && $mapLat !== null && $mapLng !== null) {
            $googleMapsUrl = 'https://www.google.com/maps?q=' . rawurlencode($mapLat . ',' . $mapLng);
        }

        $typeLabel       = OfficeWorkshop::typeLabel($type);
        $locationDisplay = $this->composeLocation($address, $city, $state);

        return [
            'slug'            => $this->str($input, 'slug', $current['slug'] ?? ''),
            'type'            => $type,
            'status'          => $this->str($input, 'status', $current['status'] ?? OfficeWorkshop::STATUS_DRAFT),
            'title'           => $title,
            'summary'         => $summary,
            'description'     => $description,
            'address'         => $address,
            'city'            => $city,
            'state'           => $state,
            'country'         => $country,
            'postal_code'     => $postalCode,
            'contact_name'    => $this->nullable($input, 'contact_name', $current['contact_name'] ?? null),
            'phone'           => $this->nullable($input, 'phone', $current['phone'] ?? null),
            'email'           => $this->nullable($input, 'email', $current['email'] ?? null),
            'whatsapp'        => $this->nullable($input, 'whatsapp', $current['whatsapp'] ?? null),
            'opening_hours'   => $this->nullable($input, 'opening_hours', $current['opening_hours'] ?? null),
            'google_maps_url' => $googleMapsUrl,
            'show_on_map'     => $showOnMap,
            'map_lat'         => $mapLat,
            'map_lng'         => $mapLng,
            'map_title'       => $title,
            'map_kind'        => $typeLabel,
            'map_location'    => $locationDisplay,
            'map_summary'     => $summary ?: $description,
            'map_image_url'   => $current['map_image_url'] ?? null,
            'map_image_alt'   => $title ?: ($current['map_image_alt'] ?? null),
            'sort_order'      => max(0, (int) $this->str($input, 'sort_order', $current['sort_order'] ?? OfficeWorkshop::nextSortOrder())),
        ];
    }

    private function validatePayload(array $payload): array
    {
        $errors = [];

        if (! in_array(($payload['type'] ?? ''), OfficeWorkshop::validTypes(), true)) {
            $errors['type'] = 'Selecciona si el registro es oficina o taller.';
        }

        if (! in_array(($payload['status'] ?? ''), OfficeWorkshop::validStatuses(), true)) {
            $errors['status'] = 'La visibilidad seleccionada no es válida.';
        }

        if (trim((string) ($payload['title'] ?? '')) === '') {
            $errors['title'] = 'El nombre es obligatorio.';
        }

        if (mb_strlen((string) ($payload['title'] ?? '')) > 255) {
            $errors['title'] = 'El nombre no debe exceder 255 caracteres.';
        }

        if (trim((string) ($payload['summary'] ?? '')) === '') {
            $errors['summary'] = 'El resumen corto es obligatorio.';
        }

        if (trim((string) ($payload['city'] ?? '')) === '') {
            $errors['city'] = 'El municipio o ciudad es obligatorio.';
        }

        if (trim((string) ($payload['state'] ?? '')) === '') {
            $errors['state'] = 'Selecciona el estado de la República.';
        } elseif (! in_array((string) $payload['state'], self::MX_STATES, true)) {
            $errors['state'] = 'Selecciona un estado válido de la República Mexicana.';
        }

        $hasLat = ($payload['map_lat'] ?? null) !== null;
        $hasLng = ($payload['map_lng'] ?? null) !== null;

        if ((int) ($payload['show_on_map'] ?? 0) === 1 && (! $hasLat || ! $hasLng)) {
            $errors['map_lat'] = 'Para mostrar el pin en el mapa, captura latitud y longitud.';
            $errors['map_lng'] = 'Para mostrar el pin en el mapa, captura latitud y longitud.';
        }

        $lat = $payload['map_lat'] ?? null;
        $lng = $payload['map_lng'] ?? null;

        if ($lat !== null && ($lat < -90 || $lat > 90)) {
            $errors['map_lat'] = 'La latitud debe estar entre -90 y 90. Ejemplo: 20.5544836.';
        }

        if ($lng !== null && ($lng < -180 || $lng > 180)) {
            $errors['map_lng'] = 'La longitud debe estar entre -180 y 180. Ejemplo: -100.4175837.';
        }

        $mapsUrl = trim((string) ($payload['google_maps_url'] ?? ''));
        if ($mapsUrl !== '' && ! $this->isHttpUrl($mapsUrl)) {
            $errors['google_maps_url'] = 'El enlace de Google Maps debe iniciar con http:// o https://.';
        }

        $email = trim((string) ($payload['email'] ?? ''));
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Escribe un correo válido.';
        }

        return $errors;
    }

    private function validateUploadedFiles(Request $request): array
    {
        $errors = [];
        $file   = $this->uploadedFile($request, 'map_image_file');

        if (! $file) {
            return $errors;
        }

        $error = $this->validateImageFile($file, 'La imagen del pin');

        if ($error !== null) {
            $errors['map_image_file'] = $error;
        }

        return $errors;
    }

    private function applyUploadedFiles(Request $request, array $payload, array $current = []): array
    {
        $file = $this->uploadedFile($request, 'map_image_file');

        if ($file) {
            $payload['map_image_url'] = $file->store(
                'office-workshops/images',
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
                if ($item && ! $this->hasUploadError($item)) {
                    return $item;
                }
            }

            return null;
        }

        if ($this->hasUploadError($file)) {
            return null;
        }

        return $file;
    }

    private function hasUploadError(mixed $file): bool
    {
        return method_exists($file, 'hasUploadError') && $file->hasUploadError();
    }

    private function validateImageFile(mixed $file, string $label): ?string
    {
        $maxBytes = 8 * 1024 * 1024;

        if (method_exists($file, 'size') && $file->size() > $maxBytes) {
            return "{$label} debe pesar máximo 8 MB.";
        }

        $mime = method_exists($file, 'type')
            ? strtolower((string) $file->type())
            : '';

        $extension = method_exists($file, 'extension')
            ? strtolower((string) $file->extension(true))
            : '';

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $allowedExts  = ['jpg', 'jpeg', 'png', 'webp'];

        if ($mime !== '' && ! in_array($mime, $allowedMimes, true)) {
            return "{$label} debe ser JPG, PNG o WEBP.";
        }

        if ($extension !== '' && ! in_array($extension, $allowedExts, true)) {
            return "{$label} debe ser JPG, PNG o WEBP.";
        }

        return null;
    }

    private function storedFiles(array $item): array
    {
        $files = [];

        foreach ([$item['map_image_url'] ?? null] as $url) {
            $path = $this->storedUploadPathFromUrl($url);

            if ($path !== null) {
                $files[] = $path;
            }
        }

        return array_values(array_unique($files));
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
        $files = array_values(array_unique(array_filter($files)));

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

        $value   = str_replace('\\', '/', $value);
        $urlPath = parse_url($value, PHP_URL_PATH);

        if (! is_string($urlPath) || trim($urlPath) === '') {
            $urlPath = $value;
        }

        $urlPath  = trim(str_replace('\\', '/', $urlPath), '/');
        $needle   = 'storage/uploads/';
        $position = strpos($urlPath, $needle);

        if ($position !== false) {
            $relative = substr($urlPath, $position + strlen($needle));
        } else {
            $relative = $urlPath;
        }

        $relative = trim(str_replace('\\', '/', $relative), '/');

        if ($relative === '' || str_contains($relative, '..') || str_contains($relative, "\0")) {
            return null;
        }

        if (! str_starts_with($relative, 'office-workshops/images/')) {
            return null;
        }

        $baseDirectory = rtrim(str_replace('\\', '/', App::$root), '/') . '/storage/uploads';
        $baseReal      = realpath($baseDirectory);

        if ($baseReal === false) {
            return null;
        }

        $baseReal  = rtrim(str_replace('\\', '/', $baseReal), '/');
        $candidate = $baseReal . '/' . $relative;

        $candidateDirectory     = dirname($candidate);
        $candidateDirectoryReal = realpath($candidateDirectory);

        if ($candidateDirectoryReal === false) {
            return null;
        }

        $candidateDirectoryReal = rtrim(str_replace('\\', '/', $candidateDirectoryReal), '/');

        if ($candidateDirectoryReal !== $baseReal && ! str_starts_with($candidateDirectoryReal, $baseReal . '/')) {
            return null;
        }

        return $candidate;
    }

    private function makeStats(array $items): array
    {
        $stats = [
            'total'     => count($items),
            'published' => 0,
            'draft'     => 0,
            'offices'   => 0,
            'workshops' => 0,
            'map'       => 0,
        ];

        foreach ($items as $item) {
            $status = $item['status'] ?? OfficeWorkshop::STATUS_DRAFT;
            $type   = $item['type'] ?? OfficeWorkshop::TYPE_OFFICE;

            if ($status === OfficeWorkshop::STATUS_PUBLISHED) {
                $stats['published']++;
            }

            if ($status === OfficeWorkshop::STATUS_DRAFT) {
                $stats['draft']++;
            }

            if ($type === OfficeWorkshop::TYPE_WORKSHOP) {
                $stats['workshops']++;
            } else {
                $stats['offices']++;
            }

            if ((int) ($item['show_on_map'] ?? 0) === 1) {
                $stats['map']++;
            }
        }

        return $stats;
    }

    private function composeLocation(?string $address, ?string $city, ?string $state): ?string
    {
        $parts = [];

        foreach ([$address, $city, $state] as $part) {
            $part = trim((string) ($part ?? ''));

            if ($part !== '' && ! in_array($part, $parts, true)) {
                $parts[] = $part;
            }
        }

        return empty($parts) ? null : implode(', ', $parts);
    }

    private function urlNullable(array $input, string $key, mixed $default = null): ?string
    {
        $value = $input[$key] ?? $default;

        if (is_array($value)) {
            return null;
        }

        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $value) && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}(\/.*)?$/i', $value)) {
            $value = 'https://' . $value;
        }

        return $value;
    }

    private function isHttpUrl(string $value): bool
    {
        $value = trim($value);

        if ($value === '') {
            return false;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function str(array $input, string $key, mixed $default = ''): string
    {
        $value = $input[$key] ?? $default;

        if (is_array($value)) {
            return '';
        }

        return trim((string) $value);
    }

    private function nullable(array $input, string $key, mixed $default = null): ?string
    {
        $value = $input[$key] ?? $default;

        if (is_array($value)) {
            return null;
        }

        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function bool(array $input, string $key): bool
    {
        return in_array((string) ($input[$key] ?? '0'), ['1', 'true', 'on', 'yes'], true);
    }

    private function floatNullable(array $input, string $key, mixed $default = null): ?float
    {
        $value = $input[$key] ?? $default;

        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
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
        if (! $user) {
            return null;
        }

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
            return 'oficina-taller';
        }

        $converted = iconv('UTF-8', 'ASCII//TRANSLIT', $text);

        if ($converted !== false) {
            $text = $converted;
        }

        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim((string) $text, '-');

        return $text ?: 'oficina-taller';
    }
}
