<?php

namespace App\Models;

use Whis\Database\Model;

class MapMarker extends Model
{
    protected ?string $table = "map_markers";

    protected string $primaryKey = "id";

    protected array $fillable = [
        "project_id",
        "type",
        "title",
        "kind",
        "location",
        "city",
        "state",
        "country",
        "latitude",
        "longitude",
        "summary",
        "href",
        "image_url",
        "image_alt",
        "marker_color",
        "is_active",
        "sort_order",
        "created_by",
        "updated_by",
        "created_at",
        "updated_at",
    ];

    public const TYPE_PROJECT = "project";
    public const TYPE_OFFICE = "office";
    public const TYPE_WORKSHOP = "workshop";

    public static function active(): array
    {
        return static::where("is_active", 1, "sort_order") ?? [];
    }

    public static function byState(string $state): array
    {
        return static::where("state", $state, "sort_order") ?? [];
    }

    public function project(): ?Project
    {
        if (empty($this->project_id)) {
            return null;
        }

        return Project::find($this->project_id);
    }
}
