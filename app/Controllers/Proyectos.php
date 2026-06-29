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

        $relatedProjects = $this->relatedProjects($project, $projectTags, 5);

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

    private function relatedProjects(array $currentProject, array $currentTags = [], int $limit = 5): array
    {
        $currentProjectId = (int) ($currentProject['id'] ?? 0);
        $limit            = max(1, min(12, $limit));

        $projects = Project::forProjectsPage();

        if (empty($projects)) {
            return [];
        }

        $candidateIds = [];

        foreach ($projects as $project) {
            $candidateId = (int) ($project['id'] ?? 0);

            if ($candidateId <= 0 || $candidateId === $currentProjectId) {
                continue;
            }

            $candidateIds[] = $candidateId;
        }

        if (empty($candidateIds)) {
            return [];
        }

        $tagsByProjectId = $this->projectTagsByProjectIds($candidateIds, ProjectTag::TYPE_TAG);
        $currentTagKeys  = $this->tagKeysFromRows($currentTags);
        $currentTerms    = $this->projectSearchTerms($currentProject, $currentTagKeys);

        $scored = [];

        foreach ($projects as $project) {
            $candidateId = (int) ($project['id'] ?? 0);

            if ($candidateId <= 0 || $candidateId === $currentProjectId) {
                continue;
            }

            $candidateTagKeys = $this->tagKeysFromRows($tagsByProjectId[$candidateId] ?? []);
            $candidateTerms   = $this->projectSearchTerms($project, $candidateTagKeys);

            $score = $this->relatedProjectScore(
                $currentProject,
                $project,
                $currentTagKeys,
                $candidateTagKeys,
                $currentTerms,
                $candidateTerms
            );

            $scored[] = [
                'project' => $project,
                'score'   => $score,
                'recent'  => $this->projectRecentValue($project),
                'id'      => $candidateId,
            ];
        }

        if (empty($scored)) {
            return [];
        }

        usort($scored, function (array $a, array $b): int {
            $scoreComparison = $b['score'] <=> $a['score'];

            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }

            $recentComparison = $b['recent'] <=> $a['recent'];

            if ($recentComparison !== 0) {
                return $recentComparison;
            }

            return $b['id'] <=> $a['id'];
        });

        $related = [];
        $usedIds = [];

        /*
         * Primero tomamos proyectos realmente relacionados.
         * Si no hay suficientes, rellenamos con los más recientes.
         */
        foreach ($scored as $item) {
            if ((int) $item['score'] <= 0) {
                continue;
            }

            $projectId = (int) $item['id'];

            $related[]           = $item['project'];
            $usedIds[$projectId] = true;

            if (count($related) >= $limit) {
                return $related;
            }
        }

        usort($scored, function (array $a, array $b): int {
            $recentComparison = $b['recent'] <=> $a['recent'];

            if ($recentComparison !== 0) {
                return $recentComparison;
            }

            $scoreComparison = $b['score'] <=> $a['score'];

            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }

            return $b['id'] <=> $a['id'];
        });

        foreach ($scored as $item) {
            $projectId = (int) $item['id'];

            if (isset($usedIds[$projectId])) {
                continue;
            }

            $related[]           = $item['project'];
            $usedIds[$projectId] = true;

            if (count($related) >= $limit) {
                break;
            }
        }

        return $related;
    }

    private function relatedProjectScore(
        array $currentProject,
        array $candidateProject,
        array $currentTagKeys,
        array $candidateTagKeys,
        array $currentTerms,
        array $candidateTerms
    ): int {
        $score = 0;

        $currentState = $this->normalizeRelatedValue(
            ($currentProject['state'] ?? '') ?: ($currentProject['map_state'] ?? '')
        );
        $candidateState = $this->normalizeRelatedValue(
            ($candidateProject['state'] ?? '') ?: ($candidateProject['map_state'] ?? '')
        );

        if ($currentState !== '' && $currentState === $candidateState) {
            $score += 55;
        }

        if ($this->sameNormalizedField($currentProject, $candidateProject, 'city')) {
            $score += 40;
        }

        foreach ([
            'category'        => 45,
            'category_badge'  => 35,
            'service'         => 35,
            'specialty'       => 30,
            'material_system' => 25,
            'map_kind'        => 20,
            'client_name'     => 10,
        ] as $field => $weight) {
            if ($this->sameNormalizedField($currentProject, $candidateProject, $field)) {
                $score += $weight;
            }
        }

        $sharedTags = array_intersect($currentTagKeys, $candidateTagKeys);

        if (! empty($sharedTags)) {
            $score += min(240, count($sharedTags) * 80);
        }

        $sharedTerms = array_intersect($currentTerms, $candidateTerms);

        if (! empty($sharedTerms)) {
            $score += min(60, count($sharedTerms) * 6);
        }

        $currentYear   = (int) ($currentProject['project_year'] ?? 0);
        $candidateYear = (int) ($candidateProject['project_year'] ?? 0);

        if ($currentYear > 0 && $candidateYear > 0) {
            $yearDistance = abs($currentYear - $candidateYear);

            if ($yearDistance === 0) {
                $score += 12;
            } elseif ($yearDistance <= 2) {
                $score += 6;
            }
        }

        return $score;
    }

    private function projectTagsByProjectIds(array $projectIds, ?string $type = null): array
    {
        $projectIds = array_values(array_unique(array_filter(array_map(
            fn($id) => (int) $id,
            $projectIds
        ))));

        if (empty($projectIds)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($projectIds), '?'));
        $params       = $projectIds;

        $sql = "SELECT *
                FROM project_tags
                WHERE project_id IN ({$placeholders})";

        if ($type !== null && $type !== '') {
            $sql      .= " AND type = ?";
            $params[]  = $type;
        }

        $sql .= " ORDER BY project_id ASC, sort_order ASC, id ASC";

        try {
            $rows = ProjectTag::getDatabaseDriver()->statement($sql, $params) ?: [];
        } catch (\Throwable $th) {
            return [];
        }

        $grouped = [];

        foreach ($rows as $row) {
            $projectId = (int) ($row['project_id'] ?? 0);

            if ($projectId <= 0) {
                continue;
            }

            $grouped[$projectId][] = $row;
        }

        return $grouped;
    }

    private function tagKeysFromRows(array $tags): array
    {
        $keys = [];

        foreach ($tags as $tag) {
            $name = is_array($tag)
                ? (string) ($tag['slug'] ?: ($tag['name'] ?? ''))
                : (string) $tag;

            $key = $this->normalizeRelatedValue($name);

            if ($key !== '') {
                $keys[$key] = true;
            }
        }

        return array_keys($keys);
    }

    private function projectSearchTerms(array $project, array $tagKeys = []): array
    {
        $source = implode(' ', array_filter([
            $project['title'] ?? '',
            $project['subtitle'] ?? '',
            $project['brief'] ?? '',
            $project['summary'] ?? '',
            $project['description'] ?? '',
            $project['category'] ?? '',
            $project['category_badge'] ?? '',
            $project['service'] ?? '',
            $project['specialty'] ?? '',
            $project['material_system'] ?? '',
            $project['scope_label'] ?? '',
            $project['city'] ?? '',
            $project['state'] ?? '',
            $project['map_kind'] ?? '',
        ]));

        $normalized = $this->normalizeRelatedValue($source);
        $words      = preg_split('/\s+/', $normalized) ?: [];

        $stopWords = [
            'de' => true, 'del' => true, 'la' => true, 'las' => true, 'el' => true,
            'los' => true, 'y' => true, 'en' => true, 'para' => true, 'por' => true,
            'con' => true, 'una' => true, 'uno' => true, 'un' => true, 'al' => true,
            'a' => true, 'o' => true, 'e' => true, 'le' => true, 'ingenieria' => true,
            'proyecto' => true, 'obra' => true, 'obras' => true, 'servicio' => true,
            'servicios' => true, 'mexico' => true,
        ];

        $terms = [];

        foreach ($words as $word) {
            $word = trim($word);

            if ($word === '' || strlen($word) < 4 || isset($stopWords[$word])) {
                continue;
            }

            $terms[$word] = true;
        }

        foreach ($tagKeys as $tagKey) {
            if ($tagKey !== '') {
                $terms[$tagKey] = true;
            }
        }

        return array_keys($terms);
    }

    private function sameNormalizedField(array $a, array $b, string $field): bool
    {
        $aValue = $this->normalizeRelatedValue($a[$field] ?? '');
        $bValue = $this->normalizeRelatedValue($b[$field] ?? '');

        return $aValue !== '' && $aValue === $bValue;
    }

    private function normalizeRelatedValue(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return '';
        }

        $converted = iconv('UTF-8', 'ASCII//TRANSLIT', $value);

        if ($converted !== false) {
            $value = $converted;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        $value = trim((string) $value);

        return preg_replace('/\s+/', ' ', $value) ?: '';
    }

    private function projectRecentValue(array $project): int
    {
        $year = (int) ($project['project_year'] ?? 0);

        $createdAt = strtotime((string) ($project['created_at'] ?? '')) ?: 0;
        $updatedAt = strtotime((string) ($project['updated_at'] ?? '')) ?: 0;

        return ($year * 10000000000)
            + max($createdAt, $updatedAt)
            + (int) ($project['id'] ?? 0);
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
