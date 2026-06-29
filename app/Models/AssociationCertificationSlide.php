<?php
namespace App\Models;

use Whis\Database\Model;

class AssociationCertificationSlide extends Model
{
    protected ?string $table = "association_certification_slides";

    protected string $primaryKey = "id";

    protected array $fillable = [
        "title",
        "short_title",
        "slug",
        "url",
        "image_url",
        "image_alt",
        "description",
        "is_active",
        "show_in_home",
        "show_in_about",
        "sort_order",
        "created_by",
        "updated_by",
        "deleted_by",
        "deleted_at",
        "created_at",
        "updated_at",
    ];

    public static function ordered(): array
    {
        $slides = static::all("sort_order") ?? [];

        return array_values(array_filter($slides, function (array $slide) {
            return empty($slide["deleted_at"]);
        }));
    }

    public static function active(): array
    {
        return array_values(array_filter(static::ordered(), function (array $slide) {
            return (int) ($slide["is_active"] ?? 0) === 1;
        }));
    }

    public static function forHome(): array
    {
        return array_values(array_filter(static::active(), function (array $slide) {
            return (int) ($slide["show_in_home"] ?? 0) === 1;
        }));
    }

    public static function forAbout(): array
    {
        return array_values(array_filter(static::active(), function (array $slide) {
            return (int) ($slide["show_in_about"] ?? 0) === 1;
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

        $set = implode(", ", array_map(
            fn ($column) => "{$column} = ?",
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

    public static function nextSortOrder(): int
    {
        $slides = static::ordered();
        $max = 0;

        foreach ($slides as $slide) {
            $max = max($max, (int) ($slide["sort_order"] ?? 0));
        }

        return $max + 1;
    }
}
