<?php
namespace App\Controllers;

use App\Models\Project;
use App\Models\ProjectMedia;
use App\Models\ProjectTag;
use Whis\Http\Controller;
use Whis\Http\Response;

class Proyectos extends Controller
{
    public function entry(string $id)
    {
        $project = Project::findBySlugOrId($id);

        if (! $project || ! $this->isPublicProject($project)) {
            if ($this->expectsJson()) {
                return $this->jsonError('Proyecto no encontrado.', [], 404);
            }

            return Response::text('Proyecto no encontrado.')->setStatus(404);
        }

        $projectId = (int) ($project['id'] ?? 0);

        $galleryMedia = ProjectMedia::galleryByProject($projectId);
        $projectTags  = ProjectTag::byProject($projectId, ProjectTag::TYPE_TAG);

        $facts       = $this->rowsByProject('project_facts', $projectId);
        $scopeItems  = $this->rowsByProject('project_scope_items', $projectId);
        $resultStats = $this->rowsByProject('project_result_stats', $projectId);

        $relatedProjects = $this->relatedProjects($projectId, 5);

        $projectEntry = $this->projectEntryPayload(
            $project,
            $galleryMedia,
            $projectTags,
            $facts,
            $scopeItems,
            $resultStats,
            $relatedProjects
        );

        if ($this->expectsJson()) {
            return $this->jsonSuccess('Proyecto encontrado.', [
                'project' => $projectEntry,
            ]);
        }

        return view('pages/project/entry', $projectEntry['seo_title'] ?: $projectEntry['title'], [
            'project'         => $project,
            'projectEntry'    => $projectEntry,
            'galleryMedia'    => $galleryMedia,
            'projectTags'     => $projectTags,
            'facts'           => $facts,
            'scopeItems'      => $scopeItems,
            'resultStats'     => $resultStats,
            'relatedProjects' => $relatedProjects,
        ], 'layouts/main');
    }

    private function isPublicProject(array $project): bool
    {
        if (! empty($project['deleted_at'])) {
            return false;
        }

        return ($project['status'] ?? '') === Project::STATUS_PUBLISHED;
    }

    private function projectEntryPayload(
        array $project,
        array $galleryMedia,
        array $projectTags,
        array $facts,
        array $scopeItems,
        array $resultStats,
        array $relatedProjects
    ): array {
        $mainImageUrl = $this->mainImageUrl($project, $galleryMedia);

        $locationDisplay = $this->locationDisplay($project);
        $googleMapsUrl   = $this->googleMapsUrl($project);

        $tags = array_values(array_filter(array_map(
            fn(array $tag) => trim((string) ($tag['name'] ?? '')),
            $projectTags
        )));

        $title = trim((string) ($project['title'] ?? ''));

        return [
            'id'               => (int) ($project['id'] ?? 0),
            'slug'             => (string) ($project['slug'] ?? ''),
            'href'             => $this->projectHref($project),
            'status'           => (string) ($project['status'] ?? ''),

            'title'            => $title,
            'subtitle'         => (string) ($project['subtitle'] ?? ''),
            'brief'            => (string) ($project['brief'] ?? ''),
            'summary'          => (string) ($project['summary'] ?? ''),
            'description'      => (string) ($project['description'] ?? ''),

            'category'         => (string) ($project['category'] ?? ''),
            'category_badge'   => (string) ($project['category_badge'] ?: ($project['category'] ?? '')),
            'tags'             => $tags,

            'year'             => (string) ($project['project_year'] ?? ''),

            'location'         => [
                'display'         => $locationDisplay,
                'municipality'    => (string) ($project['city'] ?? ''),
                'state'           => (string) ($project['state'] ?: ($project['map_state'] ?? '')),
                'country'         => (string) ($project['country'] ?? 'México'),
                'google_maps_url' => $googleMapsUrl,
                'lat'             => $project['map_lat'] ?? null,
                'lng'             => $project['map_lng'] ?? null,
            ],

            'client'           => [
                'name' => (string) ($project['client_name'] ?? ''),
                'type' => (string) ($project['client_type'] ?? ''),
            ],

            'service'          => [
                'name'            => (string) ($project['service'] ?? ''),
                'specialty'       => (string) ($project['specialty'] ?? ''),
                'material_system' => (string) ($project['material_system'] ?? ''),
                'scope'           => (string) ($project['scope_label'] ?? ''),
                'weight_label'    => (string) ($project['weight_label'] ?? ''),
                'area_label'      => (string) ($project['area_label'] ?? ''),
                'duration_label'  => (string) ($project['duration_label'] ?? ''),
            ],

            'hero'             => [
                'eyebrow'        => (string) ($project['hero_eyebrow'] ?: 'Proyecto destacado'),
                'title'          => (string) ($project['hero_title'] ?: $title),
                'copy'           => (string) ($project['hero_copy'] ?: ($project['brief'] ?: $project['summary'] ?? '')),
                'background_url' => (string) ($project['hero_background_url'] ?: $mainImageUrl),
                'button_label'   => (string) ($project['hero_button_label'] ?: 'Ver galería'),
                'button_url'     => (string) ($project['hero_button_url'] ?: '#galeria'),
            ],

            'images'           => [
                'main'      => $mainImageUrl,
                'cover'     => (string) ($project['cover_image_url'] ?: $mainImageUrl),
                'cover_alt' => (string) ($project['cover_image_alt'] ?: $title),
                'map'       => (string) ($project['map_image_url'] ?: $mainImageUrl),
                'map_alt'   => (string) ($project['map_image_alt'] ?: $title),
            ],

            'overview'         => [
                'eyebrow' => (string) ($project['overview_eyebrow'] ?: 'Portafolio'),
                'title'   => (string) ($project['overview_title'] ?: 'Ingeniería aplicada de principio a fin'),
                'body'    => (string) ($project['overview_body'] ?: ($project['description'] ?: $project['summary'] ?? '')),
            ],

            'result'           => [
                'eyebrow'      => (string) ($project['result_eyebrow'] ?: 'Resultado'),
                'title'        => (string) ($project['result_title'] ?: 'Una solución clara, segura y construible'),
                'body'         => (string) ($project['result_body'] ?: ''),
                'button_label' => (string) ($project['result_button_label'] ?: ''),
                'button_url'   => (string) ($project['result_button_url'] ?: ''),
            ],

            'map'              => Project::toMapMarker($project),

            'gallery'          => $this->mediaPayload($galleryMedia),
            'facts'            => $facts,
            'scope_items'      => $scopeItems,
            'result_stats'     => $resultStats,
            'related_projects' => $this->relatedPayload($relatedProjects),
            'seo_title'        => (string) ($project['seo_title'] ?: $title),
            'seo_description'  => (string) ($project['seo_description'] ?: ($project['brief'] ?: $project['summary'] ?? '')),
        ];
    }

    private function mediaPayload(array $galleryMedia): array
    {
        return array_values(array_map(function (array $media): array {
            $type = (string) ($media['media_type'] ?? ProjectMedia::TYPE_IMAGE);

            return [
                'id'          => (int) ($media['id'] ?? 0),
                'type'        => $type,
                'is_video'    => $type === ProjectMedia::TYPE_VIDEO,
                'is_image'    => $type === ProjectMedia::TYPE_IMAGE,
                'title'       => (string) ($media['title'] ?? ''),
                'description' => (string) ($media['description'] ?? ''),
                'file_url'    => (string) ($media['file_url'] ?? ''),
                'poster_url'  => (string) ($media['poster_url'] ?? ''),
                'alt_text'    => (string) ($media['alt_text'] ?? ''),
                'aria_label'  => (string) ($media['aria_label'] ?? ''),
                'is_featured' => (int) ($media['is_featured'] ?? 0) === 1,
                'sort_order'  => (int) ($media['sort_order'] ?? 0),
            ];
        }, $galleryMedia));
    }

    private function relatedPayload(array $projects): array
    {
        return array_values(array_map(function (array $project): array {
            $image = (string) (
                $project['cover_image_url']
                    ?: $project['hero_background_url']
                    ?: $project['map_image_url']
                    ?: ''
            );

            return [
                'id'       => (int) ($project['id'] ?? 0),
                'title'    => (string) ($project['title'] ?? ''),
                'href'     => $this->projectHref($project),
                'location' => $this->locationDisplay($project),
                'year'     => (string) ($project['project_year'] ?? ''),
                'image'    => $image,
                'imageAlt' => (string) ($project['cover_image_alt'] ?: ($project['title'] ?? '')),
            ];
        }, $projects));
    }
    private function projectHref(array $project): string
    {
        $slug = trim((string) ($project['slug'] ?? ''));

        if ($slug !== '') {
            return '/proyecto/' . $slug;
        }

        return '/proyecto/' . (int) ($project['id'] ?? 0);
    }

    private function relatedProjects(int $projectId, int $limit = 5): array
    {
        $projects = Project::forProjectsPage();

        $related = [];

        foreach ($projects as $project) {
            if ((int) ($project['id'] ?? 0) === $projectId) {
                continue;
            }

            $related[] = $project;

            if (count($related) >= $limit) {
                break;
            }
        }

        return $related;
    }

    private function rowsByProject(string $table, int $projectId): array
    {
        $allowedTables = [
            'project_facts',
            'project_scope_items',
            'project_result_stats',
        ];

        if (! in_array($table, $allowedTables, true)) {
            return [];
        }

        try {
            return Project::getDatabaseDriver()->statement(
                "SELECT * FROM {$table}
                 WHERE project_id = ?
                 ORDER BY sort_order ASC, id ASC",
                [$projectId]
            ) ?: [];
        } catch (\Throwable $th) {
            return [];
        }
    }

    private function mainImageUrl(array $project, array $galleryMedia): string
    {
        foreach ([
            $project['cover_image_url'] ?? null,
            $project['hero_background_url'] ?? null,
            $project['map_image_url'] ?? null,
        ] as $url) {
            $url = trim((string) ($url ?? ''));

            if ($url !== '') {
                return $url;
            }
        }

        foreach ($galleryMedia as $media) {
            if ((int) ($media['is_featured'] ?? 0) !== 1) {
                continue;
            }

            $url = $this->mediaImageUrl($media);

            if ($url !== '') {
                return $url;
            }
        }

        foreach ($galleryMedia as $media) {
            $url = $this->mediaImageUrl($media);

            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }

    private function mediaImageUrl(array $media): string
    {
        $type = (string) ($media['media_type'] ?? '');

        if ($type === ProjectMedia::TYPE_VIDEO) {
            return trim((string) ($media['poster_url'] ?? ''));
        }

        return trim((string) ($media['file_url'] ?? ''));
    }

    private function locationDisplay(array $project): string
    {
        $display = trim((string) ($project['location_display'] ?? ''));

        if ($display !== '') {
            return $display;
        }

        $parts = array_filter([
            trim((string) ($project['city'] ?? '')),
            trim((string) ($project['state'] ?: ($project['map_state'] ?? ''))),
            trim((string) ($project['country'] ?? 'México')),
        ]);

        return implode(', ', $parts);
    }

    private function googleMapsUrl(array $project): string
    {
        $storedUrl = trim((string) ($project['result_button_url'] ?? ''));

        if ($storedUrl !== '') {
            return $storedUrl;
        }

        $lat = trim((string) ($project['map_lat'] ?? ''));
        $lng = trim((string) ($project['map_lng'] ?? ''));

        if ($lat !== '' && $lng !== '') {
            return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($lat . ',' . $lng);
        }

        return '';
    }
}
