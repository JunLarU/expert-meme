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
        "show_in_home",
        "show_in_projects",
        "show_on_map",
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

    public const STATUS_DRAFT = "draft";
    public const STATUS_PUBLISHED = "published";
    public const STATUS_HIDDEN = "hidden";
    public const STATUS_ARCHIVED = "archived";

    public static function published(): array
    {
        return static::where("status", self::STATUS_PUBLISHED, "sort_order") ?? [];
    }

    public static function forHome(): array
    {
        return array_values(array_filter(static::published(), function (array $project) {
            return (int) ($project["show_in_home"] ?? 0) === 1
                && empty($project["deleted_at"]);
        }));
    }

    public static function forProjectsPage(): array
    {
        return array_values(array_filter(static::published(), function (array $project) {
            return (int) ($project["show_in_projects"] ?? 0) === 1
                && empty($project["deleted_at"]);
        }));
    }

    public static function newest(): ?static
    {
        return static::firstWhere("is_newest", 1, "sort_order");
    }

    public function tags(): array
    {
        return ProjectTag::where("project_id", $this->id, "sort_order") ?? [];
    }

    public function facts(): array
    {
        return ProjectFact::where("project_id", $this->id, "sort_order") ?? [];
    }

    public function media(): array
    {
        return ProjectMedia::where("project_id", $this->id, "sort_order") ?? [];
    }

    public function gallery(): array
    {
        return array_values(array_filter($this->media(), function (array $media) {
            return ($media["display_area"] ?? null) === ProjectMedia::AREA_GALLERY;
        }));
    }

    public function scopeItems(): array
    {
        return ProjectScopeItem::where("project_id", $this->id, "sort_order") ?? [];
    }

    public function resultStats(): array
    {
        return ProjectResultStat::where("project_id", $this->id, "sort_order") ?? [];
    }

    public function mapMarkers(): array
    {
        return MapMarker::where("project_id", $this->id, "sort_order") ?? [];
    }
}
