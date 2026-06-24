<?php

namespace App\Models;

use Whis\Database\Model;

class HomeJumbotronSlide extends Model
{
    protected ?string $table = "home_jumbotron_slides";

    protected string $primaryKey = "id";

    protected array $fillable = [
        "page",
        "slug",
        "status",
        "sort_order",
        "slide_class",
        "content_class",
        "button_class",
        "media_mode",
        "background_type",
        "background_url",
        "background_mobile_url",
        "background_alt",
        "video_url",
        "video_mobile_url",
        "video_poster_url",
        "video_aria_label",
        "video_preload",
        "video_controls",
        "video_autoplay",
        "video_muted",
        "video_loop",
        "video_playsinline",
        "image_url",
        "image_mobile_url",
        "image_alt",
        "image_width",
        "image_height",
        "eyebrow",
        "title",
        "subtitle",
        "body",
        "button_label",
        "button_url",
        "button_title",
        "button_aria_label",
        "button_target",
        "button_rel",
        "content_position",
        "overlay_enabled",
        "overlay_variant",
        "custom_style",
        "extra_attributes",
        "is_critical",
        "starts_at",
        "ends_at",
        "created_by",
        "updated_by",
        "created_at",
        "updated_at",
    ];

    public const STATUS_DRAFT = "draft";
    public const STATUS_PUBLISHED = "published";
    public const STATUS_HIDDEN = "hidden";

    public static function byPage(string $page = "home"): array
    {
        return static::where("page", $page, "sort_order") ?? [];
    }

    public static function published(string $page = "home"): array
    {
        $slides = static::where("page", $page, "sort_order") ?? [];

        return array_values(array_filter($slides, function (array $slide) {
            return ($slide["status"] ?? null) === self::STATUS_PUBLISHED;
        }));
    }
}
