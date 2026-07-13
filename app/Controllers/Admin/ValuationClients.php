<?php
namespace App\Controllers\Admin;

use App\Models\User;
use App\Models\ValuationClient;
use App\Models\ValuationUnit;
use Whis\App;
use Whis\Http\Controller;
use Whis\Http\Request;
use Whis\Storage\Storage;

class ValuationClients extends Controller
{
    private const MX_STATES = [
        'Querétaro',
        'Guanajuato',
    ];

    private const GUANAJUATO_CITIES = [
        'San Miguel de Allende',
        'Celaya',
        'Apaseo el Grande',
    ];

    public function index()
    {
        if ($response = $this->denyPageUnlessAdmin()) {
            return $response;
        }

        $clients = ValuationClient::ordered();

        return view('pages/admin/valuation-clients', 'Clientes de valuación', [
            'user'    => auth(),
            'clients' => $clients,
            'stats'   => $this->makeStats($clients),
        ], 'layouts/admin/layout');
    }

    public function create()
    {
        if ($response = $this->denyPageUnlessAdmin()) {
            return $response;
        }

        return view('pages/admin/valuation-client-form', 'Nuevo cliente de valuación', [
            'user' => auth(),
            'mode' => 'create',
            'client' => [
                'country' => 'México',
                'state' => 'Querétaro',
                'city' => 'Querétaro',
                'client_type' => 'empresa',
                'represented_unit' => 'valor_comercial_avaluos',
                'show_in_valuation' => 1,
                'show_in_carousel' => 1,
                'is_featured' => 0,
                'is_active' => 1,
                'sort_order' => ValuationClient::nextSortOrder(),
            ],
            'valuationUnits' => ValuationUnit::optionsForSelect(),
            'clientTypes' => ValuationClient::CLIENT_TYPES,
            'representedUnits' => ValuationClient::REPRESENTED_UNITS,
            'serviceOptions' => ValuationClient::SERVICE_OPTIONS,
            'allowedStates' => self::MX_STATES,
            'allowedGuanajuatoCities' => self::GUANAJUATO_CITIES,
        ], 'layouts/admin/layout');
    }

    public function edit(int $id)
    {
        if ($response = $this->denyPageUnlessAdmin()) {
            return $response;
        }

        $client = ValuationClient::findArray($id);

        if (! $client) {
            return redirect('/admin/valuacion/clientes');
        }

        return view('pages/admin/valuation-client-form', 'Editar cliente de valuación', [
            'user' => auth(),
            'mode' => 'edit',
            'client' => $client,
            'valuationUnits' => ValuationUnit::optionsForSelect(),
            'clientTypes' => ValuationClient::CLIENT_TYPES,
            'representedUnits' => ValuationClient::REPRESENTED_UNITS,
            'serviceOptions' => ValuationClient::SERVICE_OPTIONS,
            'allowedStates' => self::MX_STATES,
            'allowedGuanajuatoCities' => self::GUANAJUATO_CITIES,
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

        $payload['slug']       = $this->makeUniqueSlug($payload['slug'] ?: $payload['name'], 'cliente-valuacion');
        $payload['initials']   = $payload['initials'] ?: $this->initialsFromName($payload['name']);
        $payload['created_by'] = $this->userId(auth());
        $payload['updated_by'] = $this->userId(auth());

        $storedFiles = [];

        try {
            $payload = $this->applyUploadedFile($request, $payload);
            $storedFiles = $this->storedFiles($payload);

            unset($payload['remove_logo']);

            ValuationClient::create($payload);

            return $this->jsonSuccess('Cliente de valuación creado correctamente.', [
                'redirect' => '/admin/valuacion/clientes',
            ]);
        } catch (\Throwable $th) {
            $this->removeStoredFiles($storedFiles);

            return $this->jsonError('No se pudo crear el cliente de valuación.', [
                'name' => 'Revisa que el nombre o slug no estén duplicados.',
            ], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        if ($response = $this->denyJsonUnlessAdmin()) {
            return $response;
        }

        $current = ValuationClient::findArray($id);

        if (! $current) {
            return $this->jsonError('El cliente de valuación no existe.', [], 404);
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
        $payload['initials']   = $payload['initials'] ?: $this->initialsFromName($payload['name']);

        if (empty($payload['slug'])) {
            $payload['slug'] = $current['slug'] ?? $this->makeUniqueSlug($payload['name'], 'cliente-valuacion');
        } else {
            $payload['slug'] = $this->slugify($payload['slug'], 'cliente-valuacion');
        }

        $oldFiles = $this->storedFiles($current);
        $newFiles = [];

        try {
            $payload = $this->applyUploadedFile($request, $payload, $current);
            $newFiles = $this->storedFiles($payload);

            unset($payload['remove_logo']);

            ValuationClient::updateById($id, $payload);

            $this->removeReplacedStoredFiles($oldFiles, $newFiles);

            return $this->jsonSuccess('Cliente de valuación actualizado correctamente.', [
                'redirect' => '/admin/valuacion/clientes',
            ]);
        } catch (\Throwable $th) {
            $this->removeReplacedStoredFiles($newFiles, $oldFiles);

            return $this->jsonError('No se pudo actualizar el cliente de valuación.', [
                'name' => 'Revisa que los datos sean válidos.',
            ], 500);
        }
    }

    public function delete(int $id)
    {
        if ($response = $this->denyPageUnlessAdmin()) {
            return $response;
        }

        $client = ValuationClient::findArray($id);

        if (! $client) {
            return redirect('/admin/valuacion/clientes');
        }

        $files = $this->storedFiles($client);

        try {
            ValuationClient::deleteById($id);
            $this->removeStoredFiles($files);
        } catch (\Throwable $th) {
            return redirect('/admin/valuacion/clientes');
        }

        return redirect('/admin/valuacion/clientes');
    }

    public function destroy(Request $request, int $id)
    {
        if ($response = $this->denyJsonUnlessAdmin()) {
            return $response;
        }

        $client = ValuationClient::findArray($id);

        if (! $client) {
            return $this->jsonError('El cliente de valuación no existe.', [], 404);
        }

        $files = $this->storedFiles($client);

        try {
            ValuationClient::deleteById($id);
            $this->removeStoredFiles($files);

            return $this->jsonSuccess('Cliente de valuación eliminado correctamente.', [
                'redirect' => '/admin/valuacion/clientes',
            ]);
        } catch (\Throwable $th) {
            return $this->jsonError('No se pudo eliminar el cliente de valuación.', [], 500);
        }
    }

    private function payload(Request $request, array $current = []): array
    {
        $input = $request->data();
        $services = $this->serviceList($input['valuation_services'] ?? ($current['valuation_services'] ?? ''));

        return [
            'valuation_unit_id'  => $this->nullableInt($input, 'valuation_unit_id', $current['valuation_unit_id'] ?? null),
            'name'               => $this->str($input, 'name', $current['name'] ?? ''),
            'slug'               => $this->str($input, 'slug', $current['slug'] ?? ''),
            'url'                => $this->nullable($input, 'url', $current['url'] ?? null),
            'logo_url'           => $this->nullable($input, 'logo_url', $current['logo_url'] ?? null),
            'logo_alt'           => $this->nullable($input, 'logo_alt', $current['logo_alt'] ?? null),
            'initials'           => strtoupper($this->str($input, 'initials', $current['initials'] ?? '')),
            'description'        => $this->nullable($input, 'description', $current['description'] ?? null),
            'industry'           => $this->nullable($input, 'industry', $current['industry'] ?? null),
            'client_type'        => $this->enumValue($input, 'client_type', array_keys(ValuationClient::CLIENT_TYPES), $current['client_type'] ?? 'empresa'),
            'represented_unit'   => $this->enumValue($input, 'represented_unit', array_keys(ValuationClient::REPRESENTED_UNITS), $current['represented_unit'] ?? 'valor_comercial_avaluos'),
            'valuation_services' => empty($services) ? null : implode(',', $services),
            'service_summary'    => $this->nullable($input, 'service_summary', $current['service_summary'] ?? null),
            'city'               => $this->nullable($input, 'city', $current['city'] ?? null),
            'state'              => $this->nullable($input, 'state', $current['state'] ?? null),
            'country'            => $this->str($input, 'country', $current['country'] ?? 'México') ?: 'México',
            'coverage_area'      => $this->nullable($input, 'coverage_area', $current['coverage_area'] ?? null),
            'coverage_notes'     => $this->nullable($input, 'coverage_notes', $current['coverage_notes'] ?? null),
            'testimonial'        => $this->nullable($input, 'testimonial', $current['testimonial'] ?? null),
            'contact_reference'  => $this->nullable($input, 'contact_reference', $current['contact_reference'] ?? null),
            'show_in_valuation'  => $this->bool($input, 'show_in_valuation', (bool) ($current['show_in_valuation'] ?? true)),
            'show_in_carousel'   => $this->bool($input, 'show_in_carousel', (bool) ($current['show_in_carousel'] ?? true)),
            'is_featured'        => $this->bool($input, 'is_featured', (bool) ($current['is_featured'] ?? false)),
            'is_active'          => $this->bool($input, 'is_active', (bool) ($current['is_active'] ?? true)),
            'sort_order'         => max(0, (int) $this->str($input, 'sort_order', $current['sort_order'] ?? ValuationClient::nextSortOrder())),
            'remove_logo'        => $this->bool($input, 'remove_logo', false),
        ];
    }

    private function validatePayload(array $payload): array
    {
        $errors = [];

        if (trim((string) ($payload['name'] ?? '')) === '') {
            $errors['name'] = 'El nombre del cliente es obligatorio.';
        }

        if (mb_strlen((string) ($payload['name'] ?? '')) > 255) {
            $errors['name'] = 'El nombre no debe exceder 255 caracteres.';
        }

        if (mb_strlen((string) ($payload['slug'] ?? '')) > 180) {
            $errors['slug'] = 'El slug no debe exceder 180 caracteres.';
        }

        if (mb_strlen((string) ($payload['initials'] ?? '')) > 10) {
            $errors['initials'] = 'Las iniciales no deben exceder 10 caracteres.';
        }

        if (! empty($payload['url']) && ! filter_var($payload['url'], FILTER_VALIDATE_URL)) {
            $errors['url'] = 'Captura una URL válida, incluyendo http:// o https://.';
        }

        if (! in_array((string) ($payload['client_type'] ?? ''), array_keys(ValuationClient::CLIENT_TYPES), true)) {
            $errors['client_type'] = 'Selecciona un tipo de cliente válido.';
        }

        if (! in_array((string) ($payload['represented_unit'] ?? ''), array_keys(ValuationClient::REPRESENTED_UNITS), true)) {
            $errors['represented_unit'] = 'Selecciona una unidad representada válida.';
        }

        $state = trim((string) ($payload['state'] ?? ''));
        $city = trim((string) ($payload['city'] ?? ''));

        if ($state !== '' && ! in_array($state, self::MX_STATES, true)) {
            $errors['state'] = 'La cobertura de valuación solo contempla Querétaro y Guanajuato.';
        }

        if ($state === 'Guanajuato' && $city !== '' && ! in_array($city, self::GUANAJUATO_CITIES, true)) {
            $errors['city'] = 'En Guanajuato solo se permiten San Miguel de Allende, Celaya y Apaseo el Grande.';
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
                'valuation/clients/logos',
                false,
                'storage/uploads',
                true
            );
        }

        return $payload;
    }

    private function serviceList(mixed $value): array
    {
        return ValuationClient::parseServices($value);
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

    private function makeStats(array $clients): array
    {
        $active = 0;
        $inactive = 0;
        $featured = 0;
        $carousel = 0;
        $valuation = 0;
        $queretaro = 0;
        $guanajuato = 0;

        foreach ($clients as $client) {
            if ((int) ($client['is_active'] ?? 0) === 1) {
                $active++;
            } else {
                $inactive++;
            }

            if ((int) ($client['is_featured'] ?? 0) === 1) {
                $featured++;
            }

            if ((int) ($client['show_in_carousel'] ?? 0) === 1) {
                $carousel++;
            }

            if ((int) ($client['show_in_valuation'] ?? 0) === 1) {
                $valuation++;
            }

            if (($client['state'] ?? '') === 'Querétaro') {
                $queretaro++;
            }

            if (($client['state'] ?? '') === 'Guanajuato') {
                $guanajuato++;
            }
        }

        return [
            'total' => count($clients),
            'active' => $active,
            'inactive' => $inactive,
            'featured' => $featured,
            'carousel' => $carousel,
            'valuation' => $valuation,
            'queretaro' => $queretaro,
            'guanajuato' => $guanajuato,
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

    private function nullableInt(array $input, string $key, mixed $default = null): ?int
    {
        $value = $input[$key] ?? $default;

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function enumValue(array $input, string $key, array $allowed, string $default): string
    {
        $value = $this->str($input, $key, $default);

        return in_array($value, $allowed, true) ? $value : $default;
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

    private function initialsFromName(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $words = array_values(array_filter($words));

        if (empty($words)) {
            return 'VC';
        }

        if (count($words) === 1) {
            return strtoupper(mb_substr($words[0], 0, 2));
        }

        return strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
    }

    private function storedFiles(array $client): array
    {
        $path = $this->storedUploadPathFromUrl($client['logo_url'] ?? null, 'valuation/clients/logos/');

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
