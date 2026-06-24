<?php
namespace App\Models;

use Whis\Database\Model;

class ProjectMedia extends Model
{
    protected ?string $table = "project_media";

    protected string $primaryKey = "id";

    protected array $fillable = [
        "project_id",
        "media_type",
        "display_area",
        "title",
        "description",
        "file_url",
        "mobile_url",
        "poster_url",
        "alt_text",
        "aria_label",
        "width",
        "height",
        "video_preload",
        "video_controls",
        "video_autoplay",
        "video_muted",
        "video_loop",
        "video_playsinline",
        "is_featured",
        "sort_order",
        "created_by",
        "updated_by",
        "created_at",
        "updated_at",
    ];

    public const TYPE_IMAGE = "image";
    public const TYPE_VIDEO = "video";

    public const AREA_GALLERY = "gallery";
    public const AREA_HERO    = "hero";
    public const AREA_COVER   = "cover";
    public const AREA_RELATED = "related";
    public const AREA_MAP     = "map";

    public static function byProject(int $projectId, ?string $area = null): array
    {
        $instance = new static();

        $sql = "SELECT * FROM {$instance->table} WHERE project_id = ?";
        $params = [$projectId];

        if ($area !== null && $area !== "") {
            $sql .= " AND display_area = ?";
            $params[] = $area;
        }

        $sql .= " ORDER BY sort_order ASC, id ASC";

        return static::getDatabaseDriver()->statement($sql, $params) ?: [];
    }

    public static function galleryByProject(int $projectId): array
    {
        return static::byProject($projectId, self::AREA_GALLERY);
    }

    public static function findArray(int $id): ?array
    {
        $media = static::find($id);

        return $media ? $media->toArray() : null;
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

        $set = implode(", ", array_map(fn($column) => "{$column} = ?", $columns));
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

    public function project(): ?Project
    {
        return Project::find($this->project_id);
    }
}
