<?php
namespace App\Models;

use Whis\Database\Model;

class ValuationUnit extends Model
{
    protected ?string $table = 'valuation_units';

    protected string $primaryKey = 'id';

    protected array $fillable = [
        'name',
        'slug',
        'short_name',
        'role_label',
        'url',
        'logo_url',
        'logo_alt',
        'description',
        'is_primary',
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    public static function ordered(): array
    {
        $instance = new static();

        return static::getDatabaseDriver()->statement(
            "SELECT * FROM {$instance->table}
             WHERE deleted_at IS NULL
             ORDER BY sort_order ASC, id DESC"
        ) ?: [];
    }

    public static function active(): array
    {
        return array_values(array_filter(static::ordered(), function (array $unit) {
            return (int) ($unit['is_active'] ?? 0) === 1;
        }));
    }

    public static function primary(): ?array
    {
        $instance = new static();

        $rows = static::getDatabaseDriver()->statement(
            "SELECT * FROM {$instance->table}
             WHERE is_primary = 1
               AND is_active = 1
               AND deleted_at IS NULL
             ORDER BY sort_order ASC, id DESC
             LIMIT 1"
        ) ?: [];

        return $rows[0] ?? null;
    }

    public static function optionsForSelect(): array
    {
        return array_values(array_map(function (array $unit) {
            return [
                'id' => (int) ($unit['id'] ?? 0),
                'name' => (string) ($unit['name'] ?? ''),
                'short_name' => (string) (($unit['short_name'] ?? '') ?: ($unit['name'] ?? '')),
                'is_primary' => (int) ($unit['is_primary'] ?? 0),
                'is_active' => (int) ($unit['is_active'] ?? 0),
            ];
        }, static::ordered()));
    }

    public static function findArray(int $id): ?array
    {
        $unit = static::find($id);

        return $unit ? $unit->toArray() : null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $unit = static::firstWhere('slug', $slug);

        return $unit ? $unit->toArray() : null;
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

        $set = implode(', ', array_map(fn ($column) => "{$column} = ?", $columns));
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
        $units = static::ordered();
        $max = 0;

        foreach ($units as $unit) {
            $max = max($max, (int) ($unit['sort_order'] ?? 0));
        }

        return $max + 1;
    }
}
