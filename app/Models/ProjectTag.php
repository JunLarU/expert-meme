<?php
namespace App\Models;

use Whis\Database\Model;

class ProjectTag extends Model
{
    protected ?string $table = "project_tags";

    protected string $primaryKey = "id";

    protected array $fillable = [
        "project_id",
        "name",
        "slug",
        "type",
        "sort_order",
        "created_at",
        "updated_at",
    ];

    public const TYPE_TAG       = "tag";
    public const TYPE_CATEGORY  = "category";
    public const TYPE_SERVICE   = "service";
    public const TYPE_SPECIALTY = "specialty";
    public const TYPE_MATERIAL  = "material";

    public static function byProject(int $projectId, ?string $type = null): array
    {
        $instance = new static();

        $sql = "SELECT * FROM {$instance->table} WHERE project_id = ?";
        $params = [$projectId];

        if ($type !== null && $type !== "") {
            $sql .= " AND type = ?";
            $params[] = $type;
        }

        $sql .= " ORDER BY sort_order ASC, id ASC";

        return static::getDatabaseDriver()->statement($sql, $params) ?: [];
    }

    public static function syncTags(int $projectId, array $names, string $type = self::TYPE_TAG): void
    {
        $instance = new static();

        static::getDatabaseDriver()->statement(
            "DELETE FROM {$instance->table} WHERE project_id = ? AND type = ?",
            [$projectId, $type]
        );

        $sort = 1;

        foreach ($names as $name) {
            $name = trim((string) $name);

            if ($name === "") {
                continue;
            }

            static::create([
                "project_id"  => $projectId,
                "name"        => $name,
                "slug"        => static::slugify($name),
                "type"        => $type,
                "sort_order"  => $sort++,
                "created_at"  => date("Y-m-d H:i:s"),
                "updated_at"  => date("Y-m-d H:i:s"),
            ]);
        }
    }

    protected static function slugify(string $text): string
    {
        $text = trim($text);

        $converted = iconv("UTF-8", "ASCII//TRANSLIT", $text);

        if ($converted !== false) {
            $text = $converted;
        }

        $text = strtolower($text);
        $text = preg_replace("/[^a-z0-9]+/", "-", $text);
        $text = trim((string) $text, "-");

        return $text ?: "tag";
    }

    public function project(): ?Project
    {
        return Project::find($this->project_id);
    }
}
