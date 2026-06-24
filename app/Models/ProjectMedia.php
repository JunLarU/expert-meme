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
    public const AREA_HERO = "hero";
    public const AREA_COVER = "cover";
    public const AREA_RELATED = "related";

    public function project(): ?Project
    {
        return Project::find($this->project_id);
    }
}
