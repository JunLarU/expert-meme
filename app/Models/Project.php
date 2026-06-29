<?php
namespace App\Models;

use Whis\Database\Model;

class Project extends Model
{
    protected ?string $table = "projects";

    protected string $primaryKey = "id";

    protected array $fillable = [
        "slug",
        "status",
        "work_code",
        "title",
        "subtitle",
        "brief",
        "summary",
        "description",
        "category",
        "category_badge",
        "listing_number",
        "href",

        "cover_image_url",
        "cover_image_alt",
        "cover_mobile_url",

        "hero_eyebrow",
        "hero_title",
        "hero_copy",
        "hero_background_url",
        "hero_button_label",
        "hero_button_url",

        "location_display",
        "city",
        "state",
        "country",
        "project_year",

        "client_name",
        "client_type",
        "service",
        "specialty",
        "material_system",

        "weight_label",
        "area_label",
        "duration_label",
        "scope_label",

        "overview_eyebrow",
        "overview_title",
        "overview_body",

        "result_eyebrow",
        "result_title",
        "result_body",
        "result_button_label",
        "result_button_url",

        "is_featured",
        "is_newest",
        "is_home_featured",
        "show_in_home",
        "show_in_projects",
        "show_on_map",

        "map_type",
        "map_lat",
        "map_lng",
        "map_state",
        "map_title",
        "map_kind",
        "map_location",
        "map_summary",
        "map_image_url",
        "map_image_alt",

        "sort_order",

        "seo_title",
        "seo_description",

        "created_by",
        "updated_by",
        "deleted_by",
        "deleted_at",

        "created_at",
        "updated_at",
    ];

    public const STATUS_DRAFT     = "draft";
    public const STATUS_PUBLISHED = "published";
    public const STATUS_HIDDEN    = "hidden";
    public const STATUS_ARCHIVED  = "archived";

    public const MAP_PROJECT  = "project";
    public const MAP_OFFICE   = "office";
    public const MAP_WORKSHOP = "workshop";

    public static function visibleQueryWhere(): string
    {
        return "deleted_at IS NULL";
    }

    public static function allProjects(string $workCodeOrder = ''): array
    {
        $instance = new static();

        $workCodeOrder = strtolower(trim($workCodeOrder));

        $orderBy = match ($workCodeOrder) {
            'asc'   => 'work_code ASC, sort_order ASC, id DESC',
            'desc'  => 'work_code DESC, sort_order ASC, id DESC',
            default => 'sort_order ASC, id DESC',
        };

        return static::getDatabaseDriver()->statement(
            "SELECT * FROM {$instance->table}
             WHERE deleted_at IS NULL
             ORDER BY {$orderBy}"
        ) ?: [];
    }

    // app/Models/Project.php

/**
 * Busca proyectos por texto en múltiples campos.
 *
 * @param string $query      Término de búsqueda
 * @param int    $limit      Máximo de resultados (por defecto 10)
 * @return array<int, array> Lista de proyectos (estructura completa de la tabla)
 */
    // app/Models/Project.php

/**
 * Busca proyectos usando FULLTEXT MATCH AGAINST.
 *
 * @param string $query   Término de búsqueda
 * @param int    $limit   Máximo de resultados (mínimo 1, máximo 30)
 * @return array<int, array>
 */
/**
 * Busca proyectos usando FULLTEXT MATCH AGAINST con formato elástico.
 *
 * @param string $query   Término de búsqueda
 * @param int    $limit   Máximo de resultados (mínimo 1, máximo 30)
 * @return array<int, array>
 */
    /**
     * Busca proyectos publicados en todas las tablas del módulo de proyectos.
     *
     * Mantiene el nombre search2() para no romper Home::searchProjectsJson().
     * Busca en:
     * - projects
     * - project_tags
     * - project_facts
     * - project_scope_items
     * - project_result_stats
     * - project_media
     * - map_markers
     *
     * @param string $query Término de búsqueda.
     * @param int $limit Máximo de resultados.
     * @return array<int, array>
     */
    public static function search2(string $query, int $limit = 10): array
    {
        $instance = new static();

        $searchTerm = static::normalizeSearchText($query);

        if ($searchTerm === '') {
            return [];
        }

        $limit = max(1, min(30, (int) $limit));
        $words = static::searchWords($searchTerm);

        if (empty($words)) {
            return [];
        }

        $projectColumns = static::projectSearchColumns('p');
        $relatedTables  = static::relatedSearchTables();

        /*
         * search_score solo sirve para ordenar mejor:
         * primero coincidencias exactas/frase en campos principales,
         * luego tags, ubicación, ficha técnica, alcance, resultados y media.
         */
        $scoreParts  = [];
        $scoreParams = [];

        foreach ($projectColumns as $column => $weight) {
            $scoreParts[]  = "CASE WHEN {$column} LIKE ? THEN {$weight} ELSE 0 END";
            $scoreParams[] = "%{$searchTerm}%";
        }

        foreach ($relatedTables as $related) {
            $alias      = $related['alias'];
            $conditions = [];

            foreach ($related['columns'] as $column) {
                $conditions[]  = "{$alias}.{$column} LIKE ?";
                $scoreParams[] = "%{$searchTerm}%";
            }

            $scoreParts[] = "CASE WHEN EXISTS (
                SELECT 1
                FROM {$related['table']} {$alias}
                WHERE {$alias}.project_id = p.id
                  AND (" . implode(' OR ', $conditions) . ")
            ) THEN {$related['weight']} ELSE 0 END";
        }

        /*
         * Condición principal:
         * Cada palabra debe aparecer en al menos una tabla/columna del proyecto.
         * Esto permite buscar frases tipo:
         * "estructura querétaro montaje"
         * aunque cada palabra viva en una tabla distinta.
         */
        $wordWhereParts = [];
        $whereParams    = [];

        foreach ($words as $word) {
            $like       = "%{$word}%";
            $wordGroups = [];

            foreach (array_keys($projectColumns) as $column) {
                $wordGroups[]  = "{$column} LIKE ?";
                $whereParams[] = $like;
            }

            foreach ($relatedTables as $related) {
                $alias      = $related['alias'];
                $conditions = [];

                foreach ($related['columns'] as $column) {
                    $conditions[]  = "{$alias}.{$column} LIKE ?";
                    $whereParams[] = $like;
                }

                $wordGroups[] = "EXISTS (
                    SELECT 1
                    FROM {$related['table']} {$alias}
                    WHERE {$alias}.project_id = p.id
                      AND (" . implode(' OR ', $conditions) . ")
                )";
            }

            $wordWhereParts[] = '(' . implode(' OR ', $wordGroups) . ')';
        }

        $scoreSql = implode(" +\n                    ", $scoreParts);
        $whereSql = implode("\n                  AND ", $wordWhereParts);

        $sql = "SELECT p.*,
                       ({$scoreSql}) AS search_score
                FROM {$instance->table} p
                WHERE p.status = ?
                  AND p.show_in_projects = 1
                  AND p.deleted_at IS NULL
                  AND {$whereSql}
                ORDER BY search_score DESC,
                         p.sort_order ASC,
                         COALESCE(p.project_year, 0) DESC,
                         p.updated_at DESC,
                         p.id DESC
                LIMIT {$limit}";

        $params = array_merge(
            $scoreParams,
            [self::STATUS_PUBLISHED],
            $whereParams
        );

        return static::getDatabaseDriver()->statement($sql, $params) ?: [];
    }

    private static function normalizeSearchText(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/u', ' ', $value);

        return $value !== null ? trim($value) : '';
    }

    /**
     * @return array<int, string>
     */
    private static function searchWords(string $value): array
    {
        $parts = preg_split('/\s+/u', $value) ?: [];

        $words = [];

        foreach ($parts as $part) {
            $part = preg_replace('/[^\p{L}\p{N}_\-\+\.]+/u', '', (string) $part);
            $part = trim((string) $part, " \t\n\r\0\x0B.-_+");

            if ($part === '') {
                continue;
            }

            /*
             * Evita que una búsqueda como "de", "la", "en" vuelva demasiado amplia.
             * Se permiten números cortos para años, toneladas, m², etc.
             */
            if (mb_strlen($part, 'UTF-8') < 2 && ! ctype_digit($part)) {
                continue;
            }

            $words[] = $part;
        }

        return array_slice(array_values(array_unique($words)), 0, 8);
    }

    /**
     * Columnas de projects y peso para ordenar resultados.
     *
     * @return array<string, int>
     */
    private static function projectSearchColumns(string $alias): array
    {
        return [
            "{$alias}.title" => 180,
            "{$alias}.slug" => 120,
            "CAST({$alias}.work_code AS CHAR)" => 220,
            "{$alias}.subtitle" => 90,
            "{$alias}.brief" => 95,
            "{$alias}.summary" => 85,
            "{$alias}.description" => 70,

            "{$alias}.category" => 130,
            "{$alias}.category_badge" => 130,
            "{$alias}.service" => 120,
            "{$alias}.specialty" => 110,
            "{$alias}.material_system" => 100,
            "{$alias}.scope_label" => 90,

            "{$alias}.location_display" => 120,
            "{$alias}.city" => 110,
            "{$alias}.state" => 110,
            "{$alias}.country" => 40,
            "{$alias}.project_year" => 80,

            "{$alias}.client_name" => 100,
            "{$alias}.client_type" => 80,

            "{$alias}.weight_label" => 65,
            "{$alias}.area_label" => 65,
            "{$alias}.duration_label" => 55,

            "{$alias}.hero_eyebrow" => 45,
            "{$alias}.hero_title" => 85,
            "{$alias}.hero_copy" => 65,

            "{$alias}.overview_eyebrow" => 35,
            "{$alias}.overview_title" => 80,
            "{$alias}.overview_body" => 65,

            "{$alias}.result_eyebrow" => 35,
            "{$alias}.result_title" => 75,
            "{$alias}.result_body" => 65,
            "{$alias}.result_button_label" => 25,

            "{$alias}.map_state" => 90,
            "{$alias}.map_title" => 85,
            "{$alias}.map_kind" => 80,
            "{$alias}.map_location" => 90,
            "{$alias}.map_summary" => 65,
            "{$alias}.map_image_alt" => 35,

            "{$alias}.seo_title" => 70,
            "{$alias}.seo_description" => 55,
        ];
    }

    /**
     * Tablas hijas del proyecto que también participan en la búsqueda.
     *
     * @return array<int, array{table: string, alias: string, columns: array<int, string>, weight: int}>
     */
    private static function relatedSearchTables(): array
    {
        return [
            [
                'table'   => 'project_tags',
                'alias'   => 'pt',
                'columns' => ['name', 'slug', 'type'],
                'weight'  => 160,
            ],
            [
                'table'   => 'project_facts',
                'alias'   => 'pf',
                'columns' => ['label', 'value', 'icon'],
                'weight'  => 105,
            ],
            [
                'table'   => 'project_scope_items',
                'alias'   => 'psi',
                'columns' => ['number_label', 'title', 'description', 'icon'],
                'weight'  => 115,
            ],
            [
                'table'   => 'project_result_stats',
                'alias'   => 'prs',
                'columns' => ['value', 'label', 'description'],
                'weight'  => 100,
            ],
            [
                'table'   => 'project_media',
                'alias'   => 'pm',
                'columns' => ['media_type', 'display_area', 'title', 'description', 'alt_text', 'aria_label'],
                'weight'  => 75,
            ],
            [
                'table'   => 'map_markers',
                'alias'   => 'mm',
                'columns' => ['type', 'title', 'kind', 'location', 'city', 'state', 'country', 'summary', 'href', 'image_alt'],
                'weight'  => 70,
            ],
        ];
    }

    public static function published(): array
    {
        $instance = new static();

        return static::getDatabaseDriver()->statement(
            "SELECT * FROM {$instance->table}
             WHERE status = ? AND deleted_at IS NULL
             ORDER BY sort_order ASC, id DESC",
            [self::STATUS_PUBLISHED]
        ) ?: [];
    }

    public static function forProjectsPage(): array
    {
        $instance = new static();

        return static::getDatabaseDriver()->statement(
            "SELECT * FROM {$instance->table}
             WHERE status = ?
               AND show_in_projects = 1
               AND deleted_at IS NULL
             ORDER BY sort_order ASC, project_year DESC, id DESC",
            [self::STATUS_PUBLISHED]
        ) ?: [];
    }

    public static function homeFeatured(): ?array
    {
        $instance = new static();

        $rows = static::getDatabaseDriver()->statement(
            "SELECT * FROM {$instance->table}
             WHERE status = ?
               AND show_in_home = 1
               AND is_home_featured = 1
               AND deleted_at IS NULL
             ORDER BY sort_order ASC, project_year DESC, id DESC
             LIMIT 1",
            [self::STATUS_PUBLISHED]
        ) ?: [];

        return $rows[0] ?? null;
    }

    public static function latestForHome(int $limit = 3, ?int $excludeId = null): array
    {
        $instance = new static();

        $limit = max(1, min(12, $limit));

        $sql = "SELECT * FROM {$instance->table}
                WHERE status = ?
                  AND show_in_home = 1
                  AND deleted_at IS NULL";

        $params = [self::STATUS_PUBLISHED];

        if ($excludeId !== null && $excludeId > 0) {
            $sql      .= " AND id <> ?";
            $params[]  = $excludeId;
        }

        $sql .= " ORDER BY COALESCE(project_year, 0) DESC, created_at DESC, id DESC LIMIT {$limit}";

        return static::getDatabaseDriver()->statement($sql, $params) ?: [];
    }

    public static function mapMarkers(): array
    {
        $instance = new static();

        /*
     * Reglas:
     * - show_on_map = 1 => sí se muestra.
     * - show_on_map = NULL => modo automático, también se muestra.
     * - show_on_map = 0 => NO se muestra.
     *
     * - map_lat/map_lng pueden ser NULL.
     * - Si no hay coordenadas, debe existir map_state o state.
     * - El JS ya puede colocar el pin dentro del estado.
     */
        $projects = static::getDatabaseDriver()->statement(
            "SELECT * FROM {$instance->table}
         WHERE status = ?
           AND deleted_at IS NULL
           AND (
                show_on_map = 1
                OR show_on_map IS NULL
           )
           AND (
                (
                    map_lat IS NOT NULL
                    AND map_lng IS NOT NULL
                )
                OR NULLIF(TRIM(COALESCE(map_state, '')), '') IS NOT NULL
                OR NULLIF(TRIM(COALESCE(state, '')), '') IS NOT NULL
           )
         ORDER BY sort_order ASC, id DESC",
            [self::STATUS_PUBLISHED]
        ) ?: [];

        return array_values(array_filter(array_map(
            fn(array $project) => static::toMapMarker($project),
            $projects
        )));
    }

    public static function toMapMarker(array $project): ?array
    {
        $slug = trim((string) ($project["slug"] ?? ""));
        $href = trim((string) ($project["href"] ?? ""));

        if ($href === "") {
            $href = $slug !== ""
                ? "/proyecto/" . $slug
                : "/proyecto/" . (int) ($project["id"] ?? 0);
        }

        $mapType = trim((string) ($project["map_type"] ?? ""));

        if (! in_array($mapType, [self::MAP_PROJECT, self::MAP_OFFICE, self::MAP_WORKSHOP], true)) {
            $mapType = self::MAP_PROJECT;
        }

        $state = trim((string) (
            ($project["map_state"] ?? "")
                ?: ($project["state"] ?? "")
        ));

        $lat = static::nullableFloat($project["map_lat"] ?? null);
        $lng = static::nullableFloat($project["map_lng"] ?? null);

        /*
     * Si no tiene coordenadas ni estado, no se puede ubicar.
     * Pero NO forzamos valores falsos.
     */
        if (($lat === null || $lng === null) && $state === "") {
            return null;
        }

        return [
            "id"       => (int) ($project["id"] ?? 0),
            "source"   => "projects",

            /*
         * Importante:
         * Se mandan como null reales, no como 0.
         */
            "lat"      => $lat,
            "lng"      => $lng,

            "type"     => $mapType,
            "state"    => $state,

            "title"    => (string) (
                ($project["map_title"] ?? "")
                    ?: ($project["title"] ?? "Proyecto")
            ),

            "kind"     => (string) (
                ($project["map_kind"] ?? "")
                    ?: (($project["category_badge"] ?? "")
                        ?: (($project["category"] ?? "") ?: "Proyecto"))
            ),

            "location" => (string) (
                ($project["map_location"] ?? "")
                    ?: ($project["location_display"] ?? "")
            ),

            "year"     => (string) ($project["project_year"] ?? ""),

            "summary"  => (string) (
                ($project["map_summary"] ?? "")
                    ?: (($project["brief"] ?? "")
                        ?: ($project["summary"] ?? ""))
            ),

            "href"     => $href,

            "image"    => (string) (
                ($project["map_image_url"] ?? "")
                    ?: ($project["cover_image_url"] ?? "")
            ),

            "imageAlt" => (string) (
                ($project["map_image_alt"] ?? "")
                    ?: (($project["cover_image_alt"] ?? "")
                        ?: ($project["title"] ?? "Proyecto"))
            ),
        ];
    }

    private static function nullableFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === "") {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    public static function findArray(int $id): ?array
    {
        $project = static::find($id);

        return $project ? $project->toArray() : null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $project = static::firstWhere("slug", $slug);

        return $project ? $project->toArray() : null;
    }

    public static function findBySlugOrId(string $value): ?array
    {
        $value = trim($value);

        if ($value === "") {
            return null;
        }

        if (ctype_digit($value)) {
            return static::findArray((int) $value);
        }

        return static::findBySlug($value);
    }

    public static function updateById(int $id, array $attributes): bool
    {
        $instance = new static();

        $columns = [];
        $values  = [];

        foreach ($attributes as $key => $value) {
            if (! in_array($key, $instance->fillable, true)) {
                continue;
            }

            if ($key === $instance->primaryKey || $key === "created_at") {
                continue;
            }

            $columns[] = $key;
            $values[]  = $value;
        }

        if (! in_array("updated_at", $columns, true)) {
            $columns[] = "updated_at";
            $values[]  = date("Y-m-d H:i:s");
        }

        if (empty($columns)) {
            return true;
        }

        $set      = implode(", ", array_map(fn($column) => "{$column} = ?", $columns));
        $values[] = $id;

        static::getDatabaseDriver()->statement(
            "UPDATE {$instance->table} SET {$set} WHERE {$instance->primaryKey} = ?",
            $values
        );

        return true;
    }

    public static function deleteById(int $id): bool
    {
        $instance = new static();

        static::getDatabaseDriver()->statement(
            "DELETE FROM {$instance->table} WHERE {$instance->primaryKey} = ?",
            [$id]
        );

        return true;
    }

    public static function nextSortOrder(): int
    {
        $projects = static::allProjects();

        $max = 0;

        foreach ($projects as $project) {
            $max = max($max, (int) ($project["sort_order"] ?? 0));
        }

        return $max + 1;
    }

    public static function nextWorkCode(): int
    {
        $instance = new static();

        try {
            $rows = static::getDatabaseDriver()->statement(
                "SELECT MAX(work_code) AS max_code FROM {$instance->table} WHERE deleted_at IS NULL"
            ) ?: [];

            $max = (int) ($rows[0]['max_code'] ?? 0);

            return $max + 1;
        } catch (\Throwable $th) {
            /*
             * Si la columna todavía no existe en una instalación local,
             * el formulario sigue funcionando con un valor seguro.
             */
            return static::nextSortOrder();
        }
    }

    public static function forProjectsPagePaginated(int $limit = 9, int $offset = 0): array
    {
        $instance = new static();

        $limit  = max(1, min(36, $limit));
        $offset = max(0, $offset);

        return static::getDatabaseDriver()->statement(
            "SELECT * FROM {$instance->table}
         WHERE status = ?
           AND show_in_projects = 1
           AND deleted_at IS NULL
         ORDER BY sort_order ASC, COALESCE(project_year, 0) DESC, id DESC
         LIMIT {$limit} OFFSET {$offset}",
            [self::STATUS_PUBLISHED]
        ) ?: [];
    }

    public static function countForProjectsPage(): int
    {
        $instance = new static();

        $rows = static::getDatabaseDriver()->statement(
            "SELECT COUNT(*) AS total
         FROM {$instance->table}
         WHERE status = ?
           AND show_in_projects = 1
           AND deleted_at IS NULL",
            [self::STATUS_PUBLISHED]
        ) ?: [];

        return (int) ($rows[0]['total'] ?? 0);
    }

    public static function toProjectCard(array $project, int $position = 1): array
    {
        $title = trim((string) ($project['title'] ?? 'Proyecto'));

        $href = trim((string) ($project['href'] ?? ''));

        if ($href === '') {
            $slug = trim((string) ($project['slug'] ?? ''));

            $href = $slug !== ''
                ? '/proyecto/' . $slug
                : '/proyecto/' . (int) ($project['id'] ?? 0);
        }

        $image = trim((string) (
            ($project['cover_image_url'] ?? '')
                ?: ($project['hero_background_url'] ?? '')
                ?: ($project['map_image_url'] ?? '')
        ));

        if ($image === '') {
            $image = '/images/JTRON.jpg';
        }

        $category = trim((string) (
            ($project['category_badge'] ?? '')
                ?: ($project['category'] ?? '')
                ?: 'Proyecto'
        ));

        $service = trim((string) ($project['service'] ?? ''));

        /*
     * Antes:
     * tags = [categoría, servicio completo]
     *
     * Ahora:
     * "Diseño, fabricación, montaje de estructura metálica"
     * => ["Diseño", "fabricación", "montaje de estructura metálica"]
     *
     * "Diseño, fabricación y montaje de estructura metálica"
     * => ["Diseño", "fabricación", "montaje de estructura metálica"]
     */
        $tags = static::projectCardTags($category, $service);

        return [
            'id'          => (int) ($project['id'] ?? 0),
            'title'       => $title,
            'href'        => $href,
            'image'       => $image,
            'image_alt'   => (string) (($project['cover_image_alt'] ?? '') ?: $title),
            'category'    => $category,
            'service'     => $service,
            'tags'        => $tags,
            'location'    => (string) (
                ($project['location_display'] ?? '')
                    ?: trim((string) (($project['city'] ?? '') . ', ' . ($project['state'] ?? '')), ' ,')
            ),
            'year'        => (string) ($project['project_year'] ?? ''),
            'brief'       => (string) (
                ($project['brief'] ?? '')
                    ?: ($project['summary'] ?? '')
            ),
            'number'      => str_pad((string) $position, 2, '0', STR_PAD_LEFT),
            'is_featured' => $position === 1,
        ];
    }

    private static function projectCardTags(string $category, string $service): array
    {
        $tags = [];

        if (trim($category) !== '') {
            $tags[] = trim($category);
        }

        foreach (static::splitServiceTags($service) as $serviceTag) {
            $tags[] = $serviceTag;
        }

        return static::uniqueCleanTags($tags);
    }

    private static function splitServiceTags(string $service): array
    {
        $service = trim($service);

        if ($service === '') {
            return [];
        }

        /*
     * Separa por:
     * - coma: Diseño, fabricación, montaje
     * - punto y coma
     * - salto de línea
     * - " y " con espacios: fabricación y montaje
     *
     * No rompe palabras que contengan "y", porque solo separa cuando la "y"
     * está aislada entre espacios.
     */
        $parts = preg_split('/\s*(?:[,;\n\r]+|\s+y\s+)\s*/iu', $service) ?: [];

        return array_values(array_filter(array_map(
            fn($item) => trim((string) $item),
            $parts
        )));
    }

    private static function uniqueCleanTags(array $tags): array
    {
        $result = [];
        $seen   = [];

        foreach ($tags as $tag) {
            $tag = trim((string) $tag);

            if ($tag === '') {
                continue;
            }

            $key = static::normalizeTagKey($tag);

            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[]   = $tag;
        }

        return $result;
    }

    private static function normalizeTagKey(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $converted = iconv('UTF-8', 'ASCII//TRANSLIT', $value);

        if ($converted !== false) {
            $value = $converted;
        }

        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^a-z0-9]+/i', '', $value);

        return $value !== null ? $value : '';
    }
}
