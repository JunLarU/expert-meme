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

    public const STATUS_DRAFT     = "draft";
    public const STATUS_PUBLISHED = "published";
    public const STATUS_HIDDEN    = "hidden";

    public static function byPage(string $page = "home"): array
    {
        return static::where("page", $page, "sort_order") ?? [];
    }

    public static function published(string $page = "home"): array
    {
        $slides = static::byPage($page);

        return array_values(array_filter($slides, function (array $slide) {
            return static::isVisibleNow($slide);
        }));
    }

    public static function findArray(int $id): ?array
    {
        $slide = static::find($id);

        if (! $slide) {
            return null;
        }

        return $slide->toArray();
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

            if ($key === $instance->primaryKey || $key === 'created_at') {
                continue;
            }

            $columns[] = $key;
            $values[]  = $value;
        }

        if (! in_array('updated_at', $columns, true)) {
            $columns[] = 'updated_at';
            $values[]  = date('Y-m-d H:i:s');
        }

        if (empty($columns)) {
            return true;
        }

        $set = implode(', ', array_map(
            fn($column) => "{$column} = ?",
            $columns
        ));

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

    public static function nextSortOrder(string $page = "home"): int
    {
        $slides = static::byPage($page);

        $max = 0;

        foreach ($slides as $slide) {
            $max = max($max, (int) ($slide["sort_order"] ?? 0));
        }

        return $max + 1;
    }

    public static function isVisibleNow(array $slide): bool
    {
        if (($slide["status"] ?? null) !== self::STATUS_PUBLISHED) {
            return false;
        }

        $now = time();

        $startsAt = trim((string) ($slide["starts_at"] ?? ""));
        $endsAt   = trim((string) ($slide["ends_at"] ?? ""));

        if ($startsAt !== "" && strtotime($startsAt) > $now) {
            return false;
        }

        if ($endsAt !== "" && strtotime($endsAt) <= $now) {
            return false;
        }

        return true;
    }

    public static function isExpired(array $slide): bool
    {
        $endsAt = trim((string) ($slide["ends_at"] ?? ""));

        return $endsAt !== "" && strtotime($endsAt) <= time();
    }
}
