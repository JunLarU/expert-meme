<?php

namespace App\Models;

use Whis\Database\Model;

class Client extends Model
{
    protected ?string $table = "clients";

    protected string $primaryKey = "id";

    protected array $fillable = [
        "name",
        "slug",
        "url",
        "logo_url",
        "logo_alt",
        "initials",
        "description",
        "industry",
        "is_featured",
        "is_active",
        "sort_order",
        "created_by",
        "updated_by",
        "deleted_by",
        "deleted_at",
        "created_at",
        "updated_at",
    ];

    public static function active(): array
    {
        return array_values(array_filter(static::where("is_active", 1, "sort_order") ?? [], function (array $client) {
            return empty($client["deleted_at"]);
        }));
    }

    public static function featured(): array
    {
        return array_values(array_filter(static::active(), function (array $client) {
            return (int) ($client["is_featured"] ?? 0) === 1;
        }));
    }
}
