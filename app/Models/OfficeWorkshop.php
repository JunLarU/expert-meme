<?php
namespace App\Models;

use Whis\Database\Model;

class OfficeWorkshop extends Model
{
    protected ?string $table = 'office_workshops';

    protected string $primaryKey = 'id';

    protected array $fillable = [
        'slug',
        'type',
        'status',
        'title',
        'summary',
        'description',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'contact_name',
        'phone',
        'email',
        'whatsapp',
        'opening_hours',
        'google_maps_url',
        'show_on_map',
        'map_lat',
        'map_lng',
        'map_title',
        'map_kind',
        'map_location',
        'map_summary',
        'map_image_url',
        'map_image_alt',
        'sort_order',
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    public const TYPE_OFFICE   = 'office';
    public const TYPE_WORKSHOP = 'workshop';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_HIDDEN    = 'hidden';
    public const STATUS_ARCHIVED  = 'archived';

    public static function allItems(): array
    {
        $instance = new static();

        return static::getDatabaseDriver()->statement(
            "SELECT * FROM {$instance->table}
             WHERE deleted_at IS NULL
             ORDER BY sort_order ASC, id DESC"
        ) ?: [];
    }

    public static function published(): array
    {
        $instance = new static();

        return static::getDatabaseDriver()->statement(
            "SELECT * FROM {$instance->table}
             WHERE status = ?
               AND deleted_at IS NULL
             ORDER BY sort_order ASC, id DESC",
            [self::STATUS_PUBLISHED]
        ) ?: [];
    }

    public static function mapMarkers(): array
    {
        $instance = new static();

        $items = static::getDatabaseDriver()->statement(
            "SELECT * FROM {$instance->table}
             WHERE status = ?
               AND show_on_map = 1
               AND map_lat IS NOT NULL
               AND map_lng IS NOT NULL
               AND deleted_at IS NULL
             ORDER BY sort_order ASC, id DESC",
            [self::STATUS_PUBLISHED]
        ) ?: [];

        return array_values(array_map(
            fn(array $item) => static::toMapMarker($item),
            $items
        ));
    }

    public static function toMapMarker(array $item): array
    {
        $type = trim((string) ($item['type'] ?? self::TYPE_OFFICE));

        if (! in_array($type, [self::TYPE_OFFICE, self::TYPE_WORKSHOP], true)) {
            $type = self::TYPE_OFFICE;
        }

        $typeLabel = $type === self::TYPE_WORKSHOP ? 'Taller' : 'Oficina';
        $href      = trim((string) ($item['google_maps_url'] ?? ''));
        $title     = trim((string) ($item['map_title'] ?: ($item['title'] ?? $typeLabel)));

        return [
            'id'           => (int) ($item['id'] ?? 0),
            'source'       => 'office_workshops',
            'lat'          => (float) ($item['map_lat'] ?? 0),
            'lng'          => (float) ($item['map_lng'] ?? 0),
            'type'         => $type,
            'state'        => (string) ($item['state'] ?? ''),
            'title'        => $title,
            'kind'         => (string) (($item['map_kind'] ?? '') ?: $typeLabel),
            'location'     => (string) (($item['map_location'] ?? '') ?: static::composeLocation($item)),
            'year'         => '',
            'summary'      => (string) (($item['map_summary'] ?? '') ?: ($item['summary'] ?? '')),
            'href'         => $href,
            'image'        => (string) ($item['map_image_url'] ?? ''),
            'imageAlt'     => (string) (($item['map_image_alt'] ?? '') ?: $title),
            'phone'        => (string) ($item['phone'] ?? ''),
            'email'        => (string) ($item['email'] ?? ''),
            'whatsapp'     => (string) ($item['whatsapp'] ?? ''),
            'openingHours' => (string) ($item['opening_hours'] ?? ''),
        ];
    }

    public static function findArray(int $id): ?array
    {
        $item = static::find($id);

        return $item ? $item->toArray() : null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $item = static::firstWhere('slug', $slug);

        return $item ? $item->toArray() : null;
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

        $set      = implode(', ', array_map(fn($column) => "{$column} = ?", $columns));
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
        $items = static::allItems();
        $max   = 0;

        foreach ($items as $item) {
            $max = max($max, (int) ($item['sort_order'] ?? 0));
        }

        return $max + 1;
    }

    public static function typeLabel(string $type): string
    {
        return $type === self::TYPE_WORKSHOP ? 'Taller' : 'Oficina';
    }

    public static function validTypes(): array
    {
        return [self::TYPE_OFFICE, self::TYPE_WORKSHOP];
    }

    public static function validStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PUBLISHED,
            self::STATUS_HIDDEN,
            self::STATUS_ARCHIVED,
        ];
    }

    private static function composeLocation(array $item): string
    {
        $parts = [];

        foreach ([$item['address'] ?? '', $item['city'] ?? '', $item['state'] ?? ''] as $part) {
            $part = trim((string) $part);

            if ($part !== '' && ! in_array($part, $parts, true)) {
                $parts[] = $part;
            }
        }

        return implode(', ', $parts);
    }
}
