<?php
namespace App\Controllers\Admin;

use App\Models\Project;
use App\Models\ProjectMedia;
use App\Models\ProjectTag;
use Whis\App;
use Whis\Http\Controller;
use Whis\Http\Request;
use Whis\Storage\Storage;

class Projects extends Controller
{
    private const MAX_GALLERY_MEDIA = 5;

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
        if (isGuest()) {
            return redirect('/login');
        }

        $projects = Project::allProjects();

        return view('pages/admin/projects', 'Proyectos', [
            'user'     => auth(),
            'projects' => $projects,
            'stats'    => $this->makeStats($projects),
        ], 'layouts/admin/layout');
    }

    public function create()
    {
        if (isGuest()) {
            return redirect('/login');
        }

        return view('pages/admin/project-form', 'Nuevo proyecto', [
            'user'         => auth(),
            'mode'         => 'create',
            'project'      => [
                'status'           => Project::STATUS_DRAFT,
                'country'          => 'México',
                'map_type'         => Project::MAP_PROJECT,
                'show_in_home'     => 1,
                'show_in_projects' => 1,
                'show_on_map'      => 1,
                'is_featured'      => 0,
                'is_home_featured' => 0,
                'sort_order'       => Project::nextSortOrder(),
            ],
            'galleryMedia' => [],
            'projectTags'  => [],
        ], 'layouts/admin/layout');
    }

    public function edit(int $id)
    {
        if (isGuest()) {
            return redirect('/login');
        }

        $project = Project::findArray($id);

        if (! $project) {
            return redirect('/admin/proyectos');
        }

        return view('pages/admin/project-form', 'Editar proyecto', [
            'user'         => auth(),
            'mode'         => 'edit',
            'project'      => $project,
            'galleryMedia' => ProjectMedia::galleryByProject($id),
            'projectTags'  => ProjectTag::byProject($id, ProjectTag::TYPE_TAG),
        ], 'layouts/admin/layout');
    }
    public function store(Request $request)
    {
        if (isGuest()) {
            return $this->jsonError('No autorizado.', 401);
        }

        $payload = $this->payload($request);

        $errors = array_merge(
            $this->validatePayload($payload),
            $this->validateUploadedFiles($request)
        );

        if (! empty($errors)) {
            return $this->jsonError('Revisa los campos del formulario.', $errors, 422);
        }

        $payload['slug']       = $this->makeUniqueSlug($payload['slug'] ?: $payload['title']);
        $payload['href']       = '/proyecto/' . $payload['slug'];
        $payload['created_by'] = $this->userId(auth());
        $payload['updated_by'] = $this->userId(auth());

        $storedFiles = [];

        try {
            /*
             * El formulario simplificado ya no sube portada/hero/pin por separado.
             * La imagen principal se toma de la galería.
             * Dejo applyPrimaryUploadedFiles para compatibilidad si existe un form viejo.
             */
            $payload     = $this->applyPrimaryUploadedFiles($request, $payload);
            $storedFiles = $this->projectStoredFiles($payload);

            Project::create($payload);

            $created = Project::findBySlug($payload['slug']);

            if (! $created) {
                throw new \RuntimeException('No se pudo recuperar el proyecto creado.');
            }

            $projectId = (int) $created['id'];

            $this->syncProjectTags($request, $projectId);

            $galleryChanges = $this->syncGallerySlots($request, $projectId);
            $storedFiles    = array_merge($storedFiles, $galleryChanges['stored']);

            $this->applyMainGalleryMediaToProject($projectId);

            if ((int) ($payload['is_home_featured'] ?? 0) === 1) {
                $this->ensureSingleHomeFeatured($projectId);
            }

            return $this->jsonSuccess('Proyecto creado correctamente.', [
                'redirect' => '/admin/proyectos',
            ]);
        } catch (\Throwable $th) {
            $this->removeStoredFiles($storedFiles);

            return $this->jsonError('No se pudo crear el proyecto.', [
                'title' => 'Revisa que el título no esté duplicado y que los archivos sean válidos.',
            ], 500);
        }
    }
    public function update(Request $request, int $id)
    {
        if (isGuest()) {
            return $this->jsonError('No autorizado.', 401);
        }

        $current = Project::findArray($id);

        if (! $current) {
            return $this->jsonError('El proyecto no existe.', [], 404);
        }

        $payload = $this->payload($request, $current);

        $errors = array_merge(
            $this->validatePayload($payload),
            $this->validateUploadedFiles($request, $id)
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

        $payload['href'] = '/proyecto/' . $payload['slug'];

        $oldFiles = array_merge(
            $this->projectStoredFiles($current),
            $this->galleryStoredFiles(ProjectMedia::galleryByProject($id))
        );

        $newFiles               = [];
        $createdOrReplacedFiles = [];

        try {
            /*
             * Compatibilidad con formularios anteriores.
             * En el formulario simplificado, portada/hero/pin se derivan de la galería.
             */
            $payload  = $this->applyPrimaryUploadedFiles($request, $payload, $current);
            $newFiles = $this->projectStoredFiles($payload);

            Project::updateById($id, $payload);

            $galleryChanges         = $this->syncGallerySlots($request, $id);
            $createdOrReplacedFiles = $galleryChanges['stored'];

            $this->applyMainGalleryMediaToProject($id);

            if ((int) ($payload['is_home_featured'] ?? 0) === 1) {
                $this->ensureSingleHomeFeatured($id);
            }

            $this->syncProjectTags($request, $id);

            $updatedProject = Project::findArray($id) ?: [];
            $newFiles       = array_merge(
                $this->projectStoredFiles($updatedProject),
                $this->galleryStoredFiles(ProjectMedia::galleryByProject($id))
            );

            /*
             * Borramos todo archivo anterior que ya no quedó referenciado
             * por el proyecto ni por su galería.
             */
            $this->removeReplacedStoredFiles($oldFiles, $newFiles);

            /*
             * También borramos explícitamente archivos reemplazados/eliminados
             * desde slots de galería. removeStoredFile() es idempotente.
             */
            $this->removeStoredFiles($galleryChanges['removed']);

            return $this->jsonSuccess('Proyecto actualizado correctamente.', [
                'redirect' => '/admin/proyectos',
            ]);
        } catch (\Throwable $th) {
            /*
             * Si se subieron archivos nuevos pero falló el UPDATE,
             * limpiamos los nuevos para no dejar basura.
             */
            $this->removeStoredFiles($createdOrReplacedFiles);

            return $this->jsonError('No se pudo actualizar el proyecto.', [
                'title' => 'Revisa que los datos sean válidos.',
            ], 500);
        }
    }

    public function delete(int $id)
    {
        if (isGuest()) {
            return redirect('/login');
        }

        $project = Project::findArray($id);

        if (! $project) {
            return redirect('/admin/proyectos');
        }

        $gallery = ProjectMedia::galleryByProject($id);

        $files = array_merge(
            $this->projectStoredFiles($project),
            $this->galleryStoredFiles($gallery)
        );

        try {
            foreach ($gallery as $media) {
                ProjectMedia::deleteById((int) ($media['id'] ?? 0));
            }

            $this->deleteProjectTags($id);
            Project::deleteById($id);

            $this->removeStoredFiles($files);
        } catch (\Throwable $th) {
            return redirect('/admin/proyectos');
        }

        return redirect('/admin/proyectos');
    }

    public function destroy(Request $request, int $id)
    {
        if (isGuest()) {
            return $this->jsonError('No autorizado.', 401);
        }

        $project = Project::findArray($id);

        if (! $project) {
            return $this->jsonError('El proyecto no existe.', [], 404);
        }

        $gallery = ProjectMedia::galleryByProject($id);

        $files = array_merge(
            $this->projectStoredFiles($project),
            $this->galleryStoredFiles($gallery)
        );

        try {
            foreach ($gallery as $media) {
                ProjectMedia::deleteById((int) ($media['id'] ?? 0));
            }

            $this->deleteProjectTags($id);
            Project::deleteById($id);

            $this->removeStoredFiles($files);

            return $this->jsonSuccess('Proyecto eliminado correctamente.', [
                'redirect' => '/admin/proyectos',
            ]);
        } catch (\Throwable $th) {
            return $this->jsonError('No se pudo eliminar el proyecto.', [], 500);
        }
    }
    private function payload(Request $request, array $current = []): array
    {
        $input = $request->data();

        $title = $this->str($input, 'title', $current['title'] ?? '');

        $brief       = $this->nullable($input, 'brief', $current['brief'] ?? null);
        $description = $this->nullable($input, 'description', $current['description'] ?? null);
        $summary     = $brief ?: ($description ?: ($current['summary'] ?? null));

        if ($description === null) {
            $description = $summary;
        }

        $category   = $this->nullable($input, 'category', $current['category'] ?? null);
        $service    = $this->nullable($input, 'service', $current['service'] ?? null);
        $scopeLabel = $this->nullable($input, 'scope_label', $current['scope_label'] ?? null);

        $city            = $this->nullable($input, 'city', $current['city'] ?? null);
        $state           = $this->nullable($input, 'state', $current['state'] ?? null);
        $country         = 'México';
        $locationDetail  = $this->nullable($input, 'location_display', $current['location_display'] ?? null);
        $locationDisplay = $this->composeLocation($locationDetail, $city, $state);

        $projectYear = $this->intNullable($input, 'project_year', $current['project_year'] ?? null);

        $mapLat    = $this->floatNullable($input, 'map_lat', $current['map_lat'] ?? null);
        $mapLng    = $this->floatNullable($input, 'map_lng', $current['map_lng'] ?? null);
        $showOnMap = ($mapLat !== null && $mapLng !== null) ? 1 : 0;

        $googleMapsUrl = $this->urlNullable($input, 'google_maps_url', $current['result_button_url'] ?? null);

        if ($googleMapsUrl === null && $mapLat !== null && $mapLng !== null) {
            $googleMapsUrl = 'https://www.google.com/maps?q=' . rawurlencode($mapLat . ',' . $mapLng);
        }

        $heroCopy       = $summary ?: $brief ?: $description;
        $seoDescription = $brief ?: $summary ?: $description;
        $mapKind        = $category ?: ($service ?: 'Proyecto');

        return [
            'slug'                => $this->str($input, 'slug', $current['slug'] ?? ''),
            'status'              => $this->str($input, 'status', $current['status'] ?? Project::STATUS_DRAFT),
            'title'               => $title,
            'subtitle'            => null,
            'brief'               => $brief,
            'summary'             => $summary,
            'description'         => $description,
            'category'            => $category,
            'category_badge'      => $category,
            'listing_number'      => $current['listing_number'] ?? null,
            'href'                => $current['href'] ?? null,

            'cover_image_url'     => $current['cover_image_url'] ?? null,
            'cover_image_alt'     => $title ?: ($current['cover_image_alt'] ?? null),
            'cover_mobile_url'    => $current['cover_mobile_url'] ?? null,

            'hero_eyebrow'        => $category ?: 'Proyecto',
            'hero_title'          => $title,
            'hero_copy'           => $heroCopy,
            'hero_background_url' => $current['hero_background_url'] ?? null,
            'hero_button_label'   => 'Ver galería',
            'hero_button_url'     => '#galeria',

            'location_display'    => $locationDisplay,
            'city'                => $city,
            'state'               => $state,
            'country'             => $country,
            'project_year'        => $projectYear,

            'client_name'         => $this->nullable($input, 'client_name', $current['client_name'] ?? null),
            'client_type'         => null,
            'service'             => $service,
            'specialty'           => null,
            'material_system'     => null,

            'weight_label'        => $this->nullable($input, 'weight_label', $current['weight_label'] ?? null),
            'area_label'          => $this->nullable($input, 'area_label', $current['area_label'] ?? null),
            'duration_label'      => $this->nullable($input, 'duration_label', $current['duration_label'] ?? null),
            'scope_label'         => $scopeLabel,

            'overview_eyebrow'    => 'Portafolio',
            'overview_title'      => $title,
            'overview_body'       => $description ?: $summary,

            'result_eyebrow'      => null,
            'result_title'        => null,
            'result_body'         => null,
            'result_button_label' => $googleMapsUrl ? 'Ver ubicación en Google Maps' : null,
            'result_button_url'   => $googleMapsUrl,

            'is_featured'         => $this->bool($input, 'is_featured') ? 1 : 0,
            'is_newest'           => 0,
            'is_home_featured'    => $this->bool($input, 'is_home_featured') ? 1 : 0,
            'show_in_home'        => $this->bool($input, 'show_in_home') ? 1 : 0,
            'show_in_projects'    => $this->bool($input, 'show_in_projects') ? 1 : 0,
            'show_on_map'         => $showOnMap,

            'map_type'            => Project::MAP_PROJECT,
            'map_lat'             => $mapLat,
            'map_lng'             => $mapLng,
            'map_state'           => $state,
            'map_title'           => $title,
            'map_kind'            => $mapKind,
            'map_location'        => $locationDisplay,
            'map_summary'         => $brief ?: $summary,
            'map_image_url'       => $current['map_image_url'] ?? null,
            'map_image_alt'       => $title ?: ($current['map_image_alt'] ?? null),

            'sort_order'          => max(0, (int) $this->str($input, 'sort_order', $current['sort_order'] ?? Project::nextSortOrder())),

            'seo_title'           => $title,
            'seo_description'     => $seoDescription,
        ];
    }
    private function validatePayload(array $payload): array
    {
        $errors = [];

        if (trim((string) ($payload['title'] ?? '')) === '') {
            $errors['title'] = 'El nombre del proyecto es obligatorio.';
        }

        if (mb_strlen((string) ($payload['title'] ?? '')) > 255) {
            $errors['title'] = 'El nombre del proyecto no debe exceder 255 caracteres.';
        }

        if (trim((string) ($payload['brief'] ?? '')) === '') {
            $errors['brief'] = 'El resumen corto es obligatorio.';
        }

        if (! in_array(($payload['status'] ?? ''), [
            Project::STATUS_DRAFT,
            Project::STATUS_PUBLISHED,
            Project::STATUS_HIDDEN,
            Project::STATUS_ARCHIVED,
        ], true)) {
            $errors['status'] = 'La visibilidad seleccionada no es válida.';
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

        if ($hasLat xor $hasLng) {
            $errors['map_lat'] = 'Para mostrar el proyecto en el mapa, captura latitud y longitud.';
            $errors['map_lng'] = 'Para mostrar el proyecto en el mapa, captura latitud y longitud.';
        }

        $mapsUrl = trim((string) ($payload['result_button_url'] ?? ''));

        if ($mapsUrl !== '' && ! $this->isHttpUrl($mapsUrl)) {
            $errors['google_maps_url'] = 'El enlace de Google Maps debe iniciar con http:// o https://.';
        }

        return $errors;
    }
    private function validateUploadedFiles(Request $request, ?int $projectId = null): array
    {
        $errors = [];

        /*
         * Compatibilidad con campos antiguos. El nuevo formulario ya no usa
         * portadas/hero/pin separados.
         */
        foreach ([
            'cover_image_file'     => 'La portada',
            'cover_mobile_file'    => 'La portada móvil',
            'hero_background_file' => 'El fondo del hero',
            'map_image_file'       => 'La imagen del pin',
        ] as $field => $label) {
            $file = $this->uploadedFile($request, $field);

            if (! $file) {
                continue;
            }

            $error = $this->validateImageFile($file, $label);

            if ($error !== null) {
                $errors[$field] = $error;
            }
        }

        $galleryFiles = $this->uploadedFiles($request, 'gallery_media_files');
        $posterFiles  = $this->uploadedFiles($request, 'gallery_poster_files');
        $input        = $request->data();

        foreach ($galleryFiles as $index => $file) {
            if ((int) $index >= self::MAX_GALLERY_MEDIA) {
                $errors['gallery_media_files'] = 'Solo se permiten máximo 5 imágenes o videos por proyecto.';
                continue;
            }

            $error = $this->validateMediaFile($file, 'El archivo de galería');

            if ($error !== null) {
                $errors['gallery_media_files'] = $error;
            }
        }

        foreach ($posterFiles as $index => $file) {
            if ((int) $index >= self::MAX_GALLERY_MEDIA) {
                $errors['gallery_poster_files'] = 'Solo se permiten máximo 5 miniaturas de video por proyecto.';
                continue;
            }

            $error = $this->validateImageFile($file, 'La miniatura del video');

            if ($error !== null) {
                $errors['gallery_poster_files'] = $error;
            }
        }

        if ($projectId !== null) {
            $currentMedia = ProjectMedia::galleryByProject($projectId);
            $currentById  = [];

            foreach ($currentMedia as $media) {
                $mediaId = (int) ($media['id'] ?? 0);

                if ($mediaId > 0) {
                    $currentById[$mediaId] = $media;
                }
            }

            $existingIds = is_array($input['gallery_existing_ids'] ?? null)
                ? $input['gallery_existing_ids']
                : [];

            $removeValues = is_array($input['gallery_remove'] ?? null)
                ? $input['gallery_remove']
                : [];

            $usedCurrentIds = [];

            foreach ($existingIds as $slot => $mediaId) {
                $mediaId = (int) $mediaId;

                if ($mediaId <= 0 || ! isset($currentById[$mediaId])) {
                    continue;
                }

                $remove = in_array((string) ($removeValues[$slot] ?? '0'), ['1', 'true', 'on', 'yes'], true);

                if (! $remove) {
                    $usedCurrentIds[$mediaId] = true;
                }
            }

            $newAdds = 0;

            foreach ($galleryFiles as $slot => $file) {
                $existingId = (int) ($existingIds[$slot] ?? 0);

                /*
                 * Si el slot tiene archivo existente, subir un archivo cuenta como reemplazo,
                 * no como archivo extra.
                 */
                if ($existingId > 0 && isset($currentById[$existingId])) {
                    continue;
                }

                $newAdds++;
            }

            $finalTotal = count($usedCurrentIds) + $newAdds;

            if ($finalTotal > self::MAX_GALLERY_MEDIA) {
                $errors['gallery_media_files'] = 'El proyecto no puede tener más de 5 imágenes o videos. Reemplaza archivos existentes o elimina alguno.';
            }
        } elseif (count($galleryFiles) > self::MAX_GALLERY_MEDIA) {
            $errors['gallery_media_files'] = 'Solo se permiten máximo 5 imágenes o videos por proyecto.';
        }

        return $errors;
    }

    private function applyPrimaryUploadedFiles(
        Request $request,
        array $payload,
        array $current = []
    ): array {
        $coverFile       = $this->uploadedFile($request, 'cover_image_file');
        $coverMobileFile = $this->uploadedFile($request, 'cover_mobile_file');
        $heroFile        = $this->uploadedFile($request, 'hero_background_file');
        $mapFile         = $this->uploadedFile($request, 'map_image_file');

        if ($coverFile) {
            $payload['cover_image_url'] = $coverFile->store(
                'projects/images',
                false,
                'storage/uploads',
                true
            );
        }

        if ($coverMobileFile) {
            $payload['cover_mobile_url'] = $coverMobileFile->store(
                'projects/images',
                false,
                'storage/uploads',
                true
            );
        }

        if ($heroFile) {
            $payload['hero_background_url'] = $heroFile->store(
                'projects/images',
                false,
                'storage/uploads',
                true
            );
        }

        if ($mapFile) {
            $payload['map_image_url'] = $mapFile->store(
                'projects/images',
                false,
                'storage/uploads',
                true
            );
        }

        return $payload;
    }
    private function syncGallerySlots(Request $request, int $projectId): array
    {
        $input = $request->data();

        $files   = $this->uploadedFiles($request, 'gallery_media_files');
        $posters = $this->uploadedFiles($request, 'gallery_poster_files');

        $existingIds = is_array($input['gallery_existing_ids'] ?? null)
            ? $input['gallery_existing_ids']
            : [];

        $removeValues = is_array($input['gallery_remove'] ?? null)
            ? $input['gallery_remove']
            : [];

        $currentById = [];

        foreach (ProjectMedia::galleryByProject($projectId) as $media) {
            $mediaId = (int) ($media['id'] ?? 0);

            if ($mediaId > 0) {
                $currentById[$mediaId] = $media;
            }
        }

        $stored  = [];
        $removed = [];

        foreach (range(0, self::MAX_GALLERY_MEDIA - 1) as $index) {
            $existingId = (int) ($existingIds[$index] ?? 0);
            $current    = $existingId > 0 && isset($currentById[$existingId])
                ? $currentById[$existingId]
                : null;

            $remove = in_array((string) ($removeValues[$index] ?? '0'), ['1', 'true', 'on', 'yes'], true);
            $file   = $files[$index] ?? null;
            $poster = $posters[$index] ?? null;

            if ($current && $remove) {
                $removed = array_merge($removed, $this->galleryStoredFiles([$current]));
                ProjectMedia::deleteById($existingId);
                continue;
            }

            $commonData = [
                'title'       => $this->arrayValue($input, 'gallery_media_titles', $index),
                'description' => $this->arrayValue($input, 'gallery_media_descriptions', $index),
                'alt_text'    => $this->arrayValue($input, 'gallery_media_alt_texts', $index),
                'aria_label'  => $this->arrayValue($input, 'gallery_media_aria_labels', $index),
                'is_featured' => (int) $this->arrayBool($input, 'gallery_media_is_featured', $index),
                'sort_order'  => (int) ($this->arrayValue($input, 'gallery_media_sort_orders', $index) ?: $index),
                'updated_by'  => $this->userId(auth()),
                'updated_at'  => date('Y-m-d H:i:s'),
            ];

            if ($current) {
                $update = $commonData;

                if ($file) {
                    $mediaType = $this->mediaTypeOf($file);

                    if ($mediaType === null) {
                        continue;
                    }

                    $removed = array_merge($removed, $this->galleryStoredFiles([$current]));

                    $directory = $mediaType === ProjectMedia::TYPE_VIDEO
                        ? 'projects/videos'
                        : 'projects/images';

                    $fileUrl  = $file->store($directory, false, 'storage/uploads', true);
                    $stored[] = $this->storedUploadPathFromUrl($fileUrl) ?: '';

                    $posterUrl = null;

                    if ($mediaType === ProjectMedia::TYPE_VIDEO) {
                        if ($poster) {
                            $posterUrl = $poster->store('projects/images', false, 'storage/uploads', true);
                            $stored[]  = $this->storedUploadPathFromUrl($posterUrl) ?: '';
                        } else {
                            /*
                             * Si reemplaza por video y no sube miniatura, conservamos
                             * la miniatura anterior si existía.
                             */
                            $posterUrl = $current['poster_url'] ?? null;
                        }
                    }

                    $update['media_type'] = $mediaType;
                    $update['file_url']   = $fileUrl;
                    $update['poster_url'] = $posterUrl;
                    $update['mobile_url'] = null;
                } elseif ($poster && ($current['media_type'] ?? null) === ProjectMedia::TYPE_VIDEO) {
                    $removed = array_merge(
                        $removed,
                        $this->galleryStoredFiles([[
                            'file_url'   => null,
                            'mobile_url' => null,
                            'poster_url' => $current['poster_url'] ?? null,
                        ]])
                    );

                    $posterUrl            = $poster->store('projects/images', false, 'storage/uploads', true);
                    $stored[]             = $this->storedUploadPathFromUrl($posterUrl) ?: '';
                    $update['poster_url'] = $posterUrl;
                }

                ProjectMedia::updateById($existingId, $update);
                continue;
            }

            if (! $file) {
                continue;
            }

            $mediaType = $this->mediaTypeOf($file);

            if ($mediaType === null) {
                continue;
            }

            $directory = $mediaType === ProjectMedia::TYPE_VIDEO
                ? 'projects/videos'
                : 'projects/images';

            $fileUrl  = $file->store($directory, false, 'storage/uploads', true);
            $stored[] = $this->storedUploadPathFromUrl($fileUrl) ?: '';

            $posterUrl = null;

            if ($mediaType === ProjectMedia::TYPE_VIDEO && $poster) {
                $posterUrl = $poster->store('projects/images', false, 'storage/uploads', true);
                $stored[]  = $this->storedUploadPathFromUrl($posterUrl) ?: '';
            }

            ProjectMedia::create([
                'project_id'        => $projectId,
                'media_type'        => $mediaType,
                'display_area'      => ProjectMedia::AREA_GALLERY,
                'title'             => $commonData['title'],
                'description'       => $commonData['description'],
                'file_url'          => $fileUrl,
                'mobile_url'        => null,
                'poster_url'        => $posterUrl,
                'alt_text'          => $commonData['alt_text'],
                'aria_label'        => $commonData['aria_label'],
                'video_preload'     => 'none',
                'video_controls'    => 1,
                'video_autoplay'    => 0,
                'video_muted'       => 0,
                'video_loop'        => 0,
                'video_playsinline' => 1,
                'is_featured'       => $commonData['is_featured'],
                'sort_order'        => $commonData['sort_order'],
                'created_by'        => $this->userId(auth()),
                'updated_by'        => $this->userId(auth()),
                'created_at'        => date('Y-m-d H:i:s'),
                'updated_at'        => date('Y-m-d H:i:s'),
            ]);
        }

        return [
            'stored'  => array_values(array_unique(array_filter($stored))),
            'removed' => array_values(array_unique(array_filter($removed))),
        ];
    }

    private function applyMainGalleryMediaToProject(int $projectId): void
    {
        $project = Project::findArray($projectId);

        if (! $project) {
            return;
        }

        $gallery = ProjectMedia::galleryByProject($projectId);
        $main    = $this->pickMainGalleryMedia($gallery);

        if (! $main) {
            Project::updateById($projectId, [
                'cover_image_url'     => null,
                'cover_mobile_url'    => null,
                'hero_background_url' => null,
                'map_image_url'       => null,
                'updated_at'          => date('Y-m-d H:i:s'),
            ]);

            return;
        }

        $mainId = (int) ($main['id'] ?? 0);

        foreach ($gallery as $media) {
            $mediaId = (int) ($media['id'] ?? 0);

            if ($mediaId <= 0) {
                continue;
            }

            ProjectMedia::updateById($mediaId, [
                'is_featured' => $mediaId === $mainId ? 1 : 0,
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
        }

        $imageUrl = $this->imageUrlFromMedia($main);

        if ($imageUrl === null) {
            return;
        }

        $alt = trim((string) (($main['alt_text'] ?? '') ?: ($project['title'] ?? 'Proyecto')));

        Project::updateById($projectId, [
            'cover_image_url'     => $imageUrl,
            'cover_image_alt'     => $alt,
            'cover_mobile_url'    => $imageUrl,
            'hero_background_url' => $imageUrl,
            'map_image_url'       => $imageUrl,
            'map_image_alt'       => $alt,
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);
    }

    private function pickMainGalleryMedia(array $gallery): ?array
    {
        foreach ($gallery as $media) {
            if ((int) ($media['is_featured'] ?? 0) === 1 && $this->imageUrlFromMedia($media) !== null) {
                return $media;
            }
        }

        foreach ($gallery as $media) {
            if (($media['media_type'] ?? null) === ProjectMedia::TYPE_IMAGE && ! empty($media['file_url'])) {
                return $media;
            }
        }

        foreach ($gallery as $media) {
            if ($this->imageUrlFromMedia($media) !== null) {
                return $media;
            }
        }

        return null;
    }

    private function imageUrlFromMedia(array $media): ?string
    {
        if (($media['media_type'] ?? null) === ProjectMedia::TYPE_IMAGE && ! empty($media['file_url'])) {
            return (string) $media['file_url'];
        }

        if (($media['media_type'] ?? null) === ProjectMedia::TYPE_VIDEO && ! empty($media['poster_url'])) {
            return (string) $media['poster_url'];
        }

        return null;
    }

    private function syncProjectTags(Request $request, int $projectId): void
    {
        $input = $request->data();

        $raw = trim((string) ($input['tags_text'] ?? ''));

        $tags = array_filter(array_map(
            fn($item) => trim((string) $item),
            preg_split('/[,;\n]+/', $raw) ?: []
        ));

        ProjectTag::syncTags($projectId, $tags, ProjectTag::TYPE_TAG);
    }

    private function deleteProjectTags(int $projectId): void
    {
        $instance = new ProjectTag();

        ProjectTag::getDatabaseDriver()->statement(
            "DELETE FROM project_tags WHERE project_id = ?",
            [$projectId]
        );
    }

    private function ensureSingleHomeFeatured(int $projectId): void
    {
        $instance = new Project();

        Project::getDatabaseDriver()->statement(
            "UPDATE projects SET is_home_featured = 0 WHERE id <> ?",
            [$projectId]
        );
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

    private function uploadedFiles(Request $request, string $name): array
    {
        $files = $request->file($name);

        if (! $files) {
            return [];
        }

        if (! is_array($files)) {
            return $this->hasUploadError($files) ? [] : [$files];
        }

        $result = [];

        foreach ($files as $index => $file) {
            if (! $file || $this->hasUploadError($file)) {
                continue;
            }

            $result[$index] = $file;
        }

        return $result;
    }

    private function hasUploadError(mixed $file): bool
    {
        return method_exists($file, 'hasUploadError') && $file->hasUploadError();
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
            return $label . ' no tiene un formato permitido.';
        }

        return null;
    }

    private function validateImageFile(mixed $file, string $label): ?string
    {
        return $this->validateSingleFile(
            $file,
            ['png', 'jpg', 'jpeg', 'webp'],
            ['image/png', 'image/jpeg', 'image/webp'],
            6 * 1024 * 1024,
            $label
        );
    }

    private function validateVideoFile(mixed $file, string $label): ?string
    {
        return $this->validateSingleFile(
            $file,
            ['mp4', 'webm'],
            ['video/mp4', 'video/webm'],
            75 * 1024 * 1024,
            $label
        );
    }

    private function validateMediaFile(mixed $file, string $label): ?string
    {
        $type = $this->mediaTypeOf($file);

        if ($type === ProjectMedia::TYPE_IMAGE) {
            return $this->validateImageFile($file, $label);
        }

        if ($type === ProjectMedia::TYPE_VIDEO) {
            return $this->validateVideoFile($file, $label);
        }

        return $label . ' debe ser una imagen PNG, JPG, JPEG, WEBP o un video MP4/WEBM.';
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
            return ProjectMedia::TYPE_IMAGE;
        }

        if (
            str_starts_with($mime, 'video/')
            || in_array($extension, ['mp4', 'webm'], true)
        ) {
            return ProjectMedia::TYPE_VIDEO;
        }

        return null;
    }

    private function makeStats(array $projects): array
    {
        $stats = [
            'total'     => count($projects),
            'published' => 0,
            'draft'     => 0,
            'home'      => 0,
            'featured'  => 0,
            'map'       => 0,
        ];

        foreach ($projects as $project) {
            $status = $project['status'] ?? Project::STATUS_DRAFT;

            if ($status === Project::STATUS_PUBLISHED) {
                $stats['published']++;
            }

            if ($status === Project::STATUS_DRAFT) {
                $stats['draft']++;
            }

            if ((int) ($project['show_in_home'] ?? 0) === 1) {
                $stats['home']++;
            }

            if ((int) ($project['is_home_featured'] ?? 0) === 1) {
                $stats['featured']++;
            }

            if ((int) ($project['show_on_map'] ?? 0) === 1) {
                $stats['map']++;
            }
        }

        return $stats;
    }

    private function projectStoredFiles(array $project): array
    {
        $files = [];

        foreach ([
            $project['cover_image_url'] ?? null,
            $project['cover_mobile_url'] ?? null,
            $project['hero_background_url'] ?? null,
            $project['map_image_url'] ?? null,
        ] as $url) {
            $path = $this->storedUploadPathFromUrl($url);

            if ($path !== null) {
                $files[] = $path;
            }
        }

        return array_values(array_unique($files));
    }

    private function galleryStoredFiles(array $mediaRows): array
    {
        $files = [];

        foreach ($mediaRows as $media) {
            foreach ([
                $media['file_url'] ?? null,
                $media['mobile_url'] ?? null,
                $media['poster_url'] ?? null,
            ] as $url) {
                $path = $this->storedUploadPathFromUrl($url);

                if ($path !== null) {
                    $files[] = $path;
                }
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
            /*
             * DiskFileStorage::remove() borra con file_exists($path), por lo que
             * necesita recibir una ruta física real. storedUploadPathFromUrl()
             * ya devuelve esa ruta.
             */
            if (is_file($path)) {
                @unlink($path);
                return;
            }

            Storage::remove($path);
        } catch (\Throwable $th) {
            /*
             * No rompemos el CRUD por limpieza secundaria.
             */
        }
    }
    private function storedUploadPathFromUrl(mixed $url): ?string
    {
        $value = trim((string) ($url ?? ''));

        if ($value === '') {
            return null;
        }

        $value = str_replace('\\', '/', $value);

        /*
         * Puede venir como:
         * - http://localhost/storage/uploads/projects/images/archivo.webp
         * - /storage/uploads/projects/images/archivo.webp
         * - storage/uploads/projects/images/archivo.webp
         * - projects/images/archivo.webp
         * - E:/proyecto/storage/uploads/projects/images/archivo.webp
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
         * Protección: este CRUD solo borra archivos de proyectos.
         */
        if (
            ! str_starts_with($relative, 'projects/images/')
            && ! str_starts_with($relative, 'projects/videos/')
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

        $baseReal  = rtrim(str_replace('\\', '/', $baseReal), '/');
        $candidate = $baseReal . '/' . $relative;

        /*
         * Validamos el directorio aunque el archivo ya no exista.
         */
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

    private function composeLocation(?string $detail, ?string $city, ?string $state): ?string
    {
        $parts = [];

        foreach ([$detail, $city, $state] as $part) {
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

    private function intNullable(array $input, string $key, mixed $default = null): ?int
    {
        $value = $input[$key] ?? $default;

        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function floatNullable(array $input, string $key, mixed $default = null): ?float
    {
        $value = $input[$key] ?? $default;

        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function arrayValue(array $input, string $key, mixed $index): ?string
    {
        $values = $input[$key] ?? [];

        if (! is_array($values) || ! array_key_exists($index, $values)) {
            return null;
        }

        $value = $values[$index];

        if (is_array($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function arrayBool(array $input, string $key, mixed $index): bool
    {
        $values = $input[$key] ?? [];

        if (! is_array($values) || ! array_key_exists($index, $values)) {
            return false;
        }

        return in_array((string) $values[$index], ['1', 'true', 'on', 'yes'], true);
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
            return 'proyecto';
        }

        $converted = iconv('UTF-8', 'ASCII//TRANSLIT', $text);

        if ($converted !== false) {
            $text = $converted;
        }

        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim((string) $text, '-');

        return $text ?: 'proyecto';
    }

    private function mapType(string $type): string
    {
        return in_array($type, [
            Project::MAP_PROJECT,
            Project::MAP_OFFICE,
            Project::MAP_WORKSHOP,
        ], true)
            ? $type
            : Project::MAP_PROJECT;
    }
}
