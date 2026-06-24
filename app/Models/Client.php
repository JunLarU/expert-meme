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

    public static function ordered(): array
    {
        $clients = static::all("sort_order") ?? [];

        return array_values(array_filter($clients, function (array $client) {
            return empty($client["deleted_at"]);
        }));
    }

    public static function active(): array
    {
        return array_values(array_filter(static::ordered(), function (array $client) {
            return (int) ($client["is_active"] ?? 0) === 1;
        }));
    }

    public static function featured(): array
    {
        return array_values(array_filter(static::active(), function (array $client) {
            return (int) ($client["is_featured"] ?? 0) === 1;
        }));
    }

    public static function findArray(int $id): ?array
    {
        $client = static::find($id);

        if (! $client) {
            return null;
        }

        return $client->toArray();
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
        $clients = static::ordered();
        $max = 0;

        foreach ($clients as $client) {
            $max = max($max, (int) ($client["sort_order"] ?? 0));
        }

        return $max + 1;
    }
}
