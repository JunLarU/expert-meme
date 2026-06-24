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
            $payload     = $this->applyPrimaryUploadedFiles($request, $payload);
            $storedFiles = $this->projectStoredFiles($payload);

            Project::create($payload);

            $created = Project::findBySlug($payload['slug']);

            if (! $created) {
                throw new \RuntimeException('No se pudo recuperar el proyecto creado.');
            }

            $projectId = (int) $created['id'];

            $this->syncProjectTags($request, $projectId);
            $newMediaFiles = $this->storeGalleryFiles($request, $projectId);
            $storedFiles   = array_merge($storedFiles, $newMediaFiles);

            if ((int) ($payload['is_home_featured'] ?? 0) === 1) {
                $this->ensureSingleHomeFeatured($projectId);
            }

            return $this->jsonSuccess('Proyecto creado correctamente.', [
                'redirect' => '/admin/proyectos',
            ]);
        } catch (\Throwable $th) {
            $this->removeStoredFiles($storedFiles);

            return $this->jsonError('No se pudo crear el proyecto.', [
                'title' => 'Revisa que el título o slug no estén duplicados.',
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

        $newFiles = [];

        try {
            $payload  = $this->applyPrimaryUploadedFiles($request, $payload, $current);
            $newFiles = $this->projectStoredFiles($payload);

            Project::updateById($id, $payload);

            $this->updateExistingGalleryMedia($request, $id);

            $removedFiles = $this->deleteRequestedGalleryMedia($request, $id);

            /*
             * Los archivos de media nueva se suman al set de archivos finales.
             */
            $createdMediaFiles = $this->storeGalleryFiles($request, $id);
            $newFiles          = array_merge(
                $newFiles,
                $this->galleryStoredFiles(ProjectMedia::galleryByProject($id))
            );

            /*
             * Si el UPDATE fue correcto, eliminamos lo que dejó de estar referenciado:
             * cover/hero/map reemplazados, removidos, o galerías marcadas para borrar.
             */
            $this->removeReplacedStoredFiles($oldFiles, $newFiles);
            $this->removeStoredFiles($removedFiles);

            if ((int) ($payload['is_home_featured'] ?? 0) === 1) {
                $this->ensureSingleHomeFeatured($id);
            }

            $this->syncProjectTags($request, $id);

            return $this->jsonSuccess('Proyecto actualizado correctamente.', [
                'redirect' => '/admin/proyectos',
            ]);
        } catch (\Throwable $th) {
            /*
             * Si se subieron archivos nuevos y falló el UPDATE, limpiamos los nuevos.
             */
            $this->removeReplacedStoredFiles(
                array_merge($newFiles, $createdMediaFiles ?? []),
                $oldFiles
            );

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

        $coverImageUrl     = $this->nullable($input, 'cover_image_url', $current['cover_image_url'] ?? null);
        $coverMobileUrl    = $this->nullable($input, 'cover_mobile_url', $current['cover_mobile_url'] ?? null);
        $heroBackgroundUrl = $this->nullable($input, 'hero_background_url', $current['hero_background_url'] ?? null);
        $mapImageUrl       = $this->nullable($input, 'map_image_url', $current['map_image_url'] ?? null);

        if ($this->bool($input, 'remove_cover_image')) {
            $coverImageUrl = null;
        }

        if ($this->bool($input, 'remove_cover_mobile')) {
            $coverMobileUrl = null;
        }

        if ($this->bool($input, 'remove_hero_background')) {
            $heroBackgroundUrl = null;
        }

        if ($this->bool($input, 'remove_map_image')) {
            $mapImageUrl = null;
        }

        return [
            'slug'                => $this->str($input, 'slug', $current['slug'] ?? ''),
            'status'              => $this->str($input, 'status', $current['status'] ?? Project::STATUS_DRAFT),
            'title'               => $title,
            'subtitle'            => $this->nullable($input, 'subtitle', $current['subtitle'] ?? null),
            'brief'               => $this->nullable($input, 'brief', $current['brief'] ?? null),
            'summary'             => $this->nullable($input, 'summary', $current['summary'] ?? null),
            'description'         => $this->nullable($input, 'description', $current['description'] ?? null),
            'category'            => $this->nullable($input, 'category', $current['category'] ?? null),
            'category_badge'      => $this->nullable($input, 'category_badge', $current['category_badge'] ?? null),
            'listing_number'      => $this->nullable($input, 'listing_number', $current['listing_number'] ?? null),
            'href'                => $current['href'] ?? null,

            'cover_image_url'     => $coverImageUrl,
            'cover_image_alt'     => $this->nullable($input, 'cover_image_alt', $current['cover_image_alt'] ?? null),
            'cover_mobile_url'    => $coverMobileUrl,

            'hero_eyebrow'        => $this->nullable($input, 'hero_eyebrow', $current['hero_eyebrow'] ?? null),
            'hero_title'          => $this->nullable($input, 'hero_title', $current['hero_title'] ?? null),
            'hero_copy'           => $this->nullable($input, 'hero_copy', $current['hero_copy'] ?? null),
            'hero_background_url' => $heroBackgroundUrl,
            'hero_button_label'   => $this->nullable($input, 'hero_button_label', $current['hero_button_label'] ?? null),
            'hero_button_url'     => $this->nullable($input, 'hero_button_url', $current['hero_button_url'] ?? null),

            'location_display'    => $this->nullable($input, 'location_display', $current['location_display'] ?? null),
            'city'                => $this->nullable($input, 'city', $current['city'] ?? null),
            'state'               => $this->nullable($input, 'state', $current['state'] ?? null),
            'country'             => $this->str($input, 'country', $current['country'] ?? 'México'),
            'project_year'        => $this->intNullable($input, 'project_year', $current['project_year'] ?? null),

            'client_name'         => $this->nullable($input, 'client_name', $current['client_name'] ?? null),
            'client_type'         => $this->nullable($input, 'client_type', $current['client_type'] ?? null),
            'service'             => $this->nullable($input, 'service', $current['service'] ?? null),
            'specialty'           => $this->nullable($input, 'specialty', $current['specialty'] ?? null),
            'material_system'     => $this->nullable($input, 'material_system', $current['material_system'] ?? null),

            'weight_label'        => $this->nullable($input, 'weight_label', $current['weight_label'] ?? null),
            'area_label'          => $this->nullable($input, 'area_label', $current['area_label'] ?? null),
            'duration_label'      => $this->nullable($input, 'duration_label', $current['duration_label'] ?? null),
            'scope_label'         => $this->nullable($input, 'scope_label', $current['scope_label'] ?? null),

            'overview_eyebrow'    => $this->nullable($input, 'overview_eyebrow', $current['overview_eyebrow'] ?? null),
            'overview_title'      => $this->nullable($input, 'overview_title', $current['overview_title'] ?? null),
            'overview_body'       => $this->nullable($input, 'overview_body', $current['overview_body'] ?? null),

            'result_eyebrow'      => $this->nullable($input, 'result_eyebrow', $current['result_eyebrow'] ?? null),
            'result_title'        => $this->nullable($input, 'result_title', $current['result_title'] ?? null),
            'result_body'         => $this->nullable($input, 'result_body', $current['result_body'] ?? null),
            'result_button_label' => $this->nullable($input, 'result_button_label', $current['result_button_label'] ?? null),
            'result_button_url'   => $this->nullable($input, 'result_button_url', $current['result_button_url'] ?? null),

            'is_featured'         => $this->bool($input, 'is_featured') ? 1 : 0,
            'is_newest'           => 0,
            'is_home_featured'    => $this->bool($input, 'is_home_featured') ? 1 : 0,
            'show_in_home'        => $this->bool($input, 'show_in_home') ? 1 : 0,
            'show_in_projects'    => $this->bool($input, 'show_in_projects') ? 1 : 0,
            'show_on_map'         => $this->bool($input, 'show_on_map') ? 1 : 0,

            'map_type'            => $this->mapType($this->str($input, 'map_type', $current['map_type'] ?? Project::MAP_PROJECT)),
            'map_lat'             => $this->floatNullable($input, 'map_lat', $current['map_lat'] ?? null),
            'map_lng'             => $this->floatNullable($input, 'map_lng', $current['map_lng'] ?? null),
            'map_state'           => $this->nullable($input, 'map_state', $current['map_state'] ?? null),
            'map_title'           => $this->nullable($input, 'map_title', $current['map_title'] ?? null),
            'map_kind'            => $this->nullable($input, 'map_kind', $current['map_kind'] ?? null),
            'map_location'        => $this->nullable($input, 'map_location', $current['map_location'] ?? null),
            'map_summary'         => $this->nullable($input, 'map_summary', $current['map_summary'] ?? null),
            'map_image_url'       => $mapImageUrl,
            'map_image_alt'       => $this->nullable($input, 'map_image_alt', $current['map_image_alt'] ?? null),

            'sort_order'          => max(0, (int) $this->str($input, 'sort_order', $current['sort_order'] ?? Project::nextSortOrder())),

            'seo_title'           => $this->nullable($input, 'seo_title', $current['seo_title'] ?? null),
            'seo_description'     => $this->nullable($input, 'seo_description', $current['seo_description'] ?? null),
        ];
    }

    private function validatePayload(array $payload): array
    {
        $errors = [];

        if (trim((string) ($payload['title'] ?? '')) === '') {
            $errors['title'] = 'El título del proyecto es obligatorio.';
        }

        if (! in_array(($payload['status'] ?? ''), [
            Project::STATUS_DRAFT,
            Project::STATUS_PUBLISHED,
            Project::STATUS_HIDDEN,
            Project::STATUS_ARCHIVED,
        ], true)) {
            $errors['status'] = 'El estado seleccionado no es válido.';
        }

        if (! in_array(($payload['map_type'] ?? ''), [
            Project::MAP_PROJECT,
            Project::MAP_OFFICE,
            Project::MAP_WORKSHOP,
        ], true)) {
            $errors['map_type'] = 'El tipo de pin del mapa no es válido.';
        }

        if ((int) ($payload['show_on_map'] ?? 0) === 1) {
            if ($payload['map_lat'] === null) {
                $errors['map_lat'] = 'La latitud es obligatoria si el proyecto se mostrará en el mapa.';
            }

            if ($payload['map_lng'] === null) {
                $errors['map_lng'] = 'La longitud es obligatoria si el proyecto se mostrará en el mapa.';
            }

            if (trim((string) ($payload['map_state'] ?: $payload['state'] ?? '')) === '') {
                $errors['map_state'] = 'El estado del mapa es obligatorio para agrupar los pines.';
            }
        }

        return $errors;
    }

    private function validateUploadedFiles(Request $request, ?int $projectId = null): array
    {
        $errors = [];

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

        if (count($galleryFiles) > self::MAX_GALLERY_MEDIA) {
            $errors['gallery_media_files'] = 'Solo se permiten máximo 5 imágenes o videos por proyecto.';
        }

        foreach ($galleryFiles as $index => $file) {
            if ((int) $index >= self::MAX_GALLERY_MEDIA) {
                $errors['gallery_media_files'] = 'Solo se permiten máximo 5 imágenes o videos por proyecto.';
                continue;
            }

            if (! $file) {
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

            if (! $file) {
                continue;
            }

            $error = $this->validateImageFile($file, 'La miniatura del video');

            if ($error !== null) {
                $errors['gallery_poster_files'] = $error;
            }
        }

        /*
     * En edición, también evitamos que el usuario deje más de 5 entre:
     * - archivos existentes que no marcó para eliminar
     * - archivos nuevos que está subiendo
     */
        if ($projectId !== null) {
            $input = $request->data();

            $removeIds = array_map(
                'intval',
                is_array($input['remove_media_ids'] ?? null)
                    ? $input['remove_media_ids']
                    : []
            );

            $removeLookup = array_flip($removeIds);

            $currentMedia = ProjectMedia::galleryByProject($projectId);

            $remainingCurrent = 0;

            foreach ($currentMedia as $media) {
                $mediaId = (int) ($media['id'] ?? 0);

                if ($mediaId <= 0) {
                    continue;
                }

                if (isset($removeLookup[$mediaId])) {
                    continue;
                }

                $remainingCurrent++;
            }

            $finalTotal = $remainingCurrent + count($galleryFiles);

            if ($finalTotal > self::MAX_GALLERY_MEDIA) {
                $errors['gallery_media_files'] = 'El proyecto no puede tener más de 5 imágenes o videos en galería. Elimina archivos actuales o sube menos archivos nuevos.';
            }
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

    private function storeGalleryFiles(Request $request, int $projectId): array
    {
        $input = $request->data();

        $files   = $this->uploadedFiles($request, 'gallery_media_files');
        $posters = $this->uploadedFiles($request, 'gallery_poster_files');

        $stored = [];

        foreach ($files as $index => $file) {
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

            if ($mediaType === ProjectMedia::TYPE_VIDEO && isset($posters[$index]) && $posters[$index]) {
                $posterUrl = $posters[$index]->store('projects/images', false, 'storage/uploads', true);
                $stored[]  = $this->storedUploadPathFromUrl($posterUrl) ?: '';
            }

            ProjectMedia::create([
                'project_id'        => $projectId,
                'media_type'        => $mediaType,
                'display_area'      => ProjectMedia::AREA_GALLERY,
                'title'             => $this->arrayValue($input, 'gallery_media_titles', $index),
                'description'       => $this->arrayValue($input, 'gallery_media_descriptions', $index),
                'file_url'          => $fileUrl,
                'poster_url'        => $posterUrl,
                'alt_text'          => $this->arrayValue($input, 'gallery_media_alt_texts', $index),
                'aria_label'        => $this->arrayValue($input, 'gallery_media_aria_labels', $index),
                'video_preload'     => 'none',
                'video_controls'    => 1,
                'video_autoplay'    => 0,
                'video_muted'       => 0,
                'video_loop'        => 0,
                'video_playsinline' => 1,
                'is_featured'       => (int) $this->arrayBool($input, 'gallery_media_is_featured', $index),
                'sort_order'        => (int) ($this->arrayValue($input, 'gallery_media_sort_orders', $index) ?: 0),
                'created_by'        => $this->userId(auth()),
                'updated_by'        => $this->userId(auth()),
                'created_at'        => date('Y-m-d H:i:s'),
                'updated_at'        => date('Y-m-d H:i:s'),
            ]);
        }

        return array_values(array_filter($stored));
    }

    private function updateExistingGalleryMedia(Request $request, int $projectId): void
    {
        $input = $request->data();

        $existing = $input['existing_media'] ?? [];

        if (! is_array($existing)) {
            return;
        }

        foreach ($existing as $mediaId => $data) {
            $mediaId = (int) $mediaId;

            if ($mediaId <= 0 || ! is_array($data)) {
                continue;
            }

            $current = ProjectMedia::findArray($mediaId);

            if (! $current || (int) ($current['project_id'] ?? 0) !== $projectId) {
                continue;
            }

            ProjectMedia::updateById($mediaId, [
                'title'       => $this->nullable($data, 'title', $current['title'] ?? null),
                'description' => $this->nullable($data, 'description', $current['description'] ?? null),
                'alt_text'    => $this->nullable($data, 'alt_text', $current['alt_text'] ?? null),
                'aria_label'  => $this->nullable($data, 'aria_label', $current['aria_label'] ?? null),
                'is_featured' => $this->bool($data, 'is_featured') ? 1 : 0,
                'sort_order'  => max(0, (int) $this->str($data, 'sort_order', $current['sort_order'] ?? 0)),
                'updated_by'  => $this->userId(auth()),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function deleteRequestedGalleryMedia(Request $request, int $projectId): array
    {
        $input = $request->data();

        $ids = $input['remove_media_ids'] ?? [];

        if (! is_array($ids)) {
            return [];
        }

        $removedFiles = [];

        foreach ($ids as $id) {
            $mediaId = (int) $id;

            if ($mediaId <= 0) {
                continue;
            }

            $media = ProjectMedia::findArray($mediaId);

            if (! $media || (int) ($media['project_id'] ?? 0) !== $projectId) {
                continue;
            }

            $removedFiles = array_merge($removedFiles, $this->galleryStoredFiles([$media]));

            ProjectMedia::deleteById($mediaId);
        }

        return $removedFiles;
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

        $urlPath = parse_url($value, PHP_URL_PATH);

        if (! is_string($urlPath) || trim($urlPath) === '') {
            $urlPath = $value;
        }

        $urlPath = trim(str_replace('\\', '/', $urlPath), '/');

        $needle   = 'storage/uploads/';
        $position = strpos($urlPath, $needle);

        if ($position === false) {
            return null;
        }

        $relative = substr($urlPath, $position + strlen($needle));
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

        $baseReal           = rtrim(str_replace('\\', '/', $baseReal), '/');
        $candidate          = $baseReal . '/' . $relative;
        $candidateDirectory = realpath(dirname($candidate));

        if ($candidateDirectory === false) {
            return null;
        }

        $candidateDirectory = rtrim(str_replace('\\', '/', $candidateDirectory), '/');

        if (
            $candidateDirectory !== $baseReal
            && ! str_starts_with($candidateDirectory, $baseReal . '/')
        ) {
            return null;
        }

        return $relative;
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
