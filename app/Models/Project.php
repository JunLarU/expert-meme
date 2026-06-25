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

    public static function allProjects(): array
    {
        $instance = new static();

        return static::getDatabaseDriver()->statement(
            "SELECT * FROM {$instance->table}
             WHERE deleted_at IS NULL
             ORDER BY sort_order ASC, id DESC"
        ) ?: [];
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

        $projects = static::getDatabaseDriver()->statement(
            "SELECT * FROM {$instance->table}
             WHERE status = ?
               AND show_on_map = 1
               AND map_lat IS NOT NULL
               AND map_lng IS NOT NULL
               AND deleted_at IS NULL
             ORDER BY sort_order ASC, id DESC",
            [self::STATUS_PUBLISHED]
        ) ?: [];

        return array_values(array_map(
            fn(array $project) => static::toMapMarker($project),
            $projects
        ));
    }

    public static function toMapMarker(array $project): array
    {
        $slug = trim((string) ($project["slug"] ?? ""));
        $href = trim((string) ($project["href"] ?? ""));

        if ($href === "" && $slug !== "") {
            $href = "/proyecto/" . $slug;
        }

        $mapType = trim((string) ($project["map_type"] ?? ""));
        if (! in_array($mapType, [self::MAP_PROJECT, self::MAP_OFFICE, self::MAP_WORKSHOP], true)) {
            $mapType = self::MAP_PROJECT;
        }

        return [
            "id"       => (int) ($project["id"] ?? 0),
            "lat"      => (float) ($project["map_lat"] ?? 0),
            "lng"      => (float) ($project["map_lng"] ?? 0),
            "type"     => $mapType,
            "state"    => (string) ($project["map_state"] ?: ($project["state"] ?? "")),
            "title"    => (string) ($project["map_title"] ?: ($project["title"] ?? "")),
            "kind"     => (string) ($project["map_kind"] ?: ($project["category_badge"] ?: ($project["category"] ?? "Proyecto"))),
            "location" => (string) ($project["map_location"] ?: ($project["location_display"] ?? "")),
            "year"     => (string) ($project["project_year"] ?? ""),
            "summary"  => (string) ($project["map_summary"] ?: ($project["brief"] ?: ($project["summary"] ?? ""))),
            "href"     => $href,
            "image"    => (string) ($project["map_image_url"] ?: ($project["cover_image_url"] ?? "")),
            "imageAlt" => (string) ($project["map_image_alt"] ?: ($project["cover_image_alt"] ?: ($project["title"] ?? ""))),
        ];
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

        $tags = array_values(array_filter([
            $category,
            $service,
        ]));

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
}
