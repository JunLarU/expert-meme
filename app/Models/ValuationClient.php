<?php
namespace App\Models;

use Whis\Database\Model;

class ValuationClient extends Model
{
    protected ?string $table = 'valuation_clients';

    protected string $primaryKey = 'id';

    protected array $fillable = [
        'valuation_unit_id',
        'name',
        'slug',
        'url',
        'logo_url',
        'logo_alt',
        'initials',
        'description',
        'industry',
        'client_type',
        'represented_unit',
        'valuation_services',
        'service_summary',
        'city',
        'state',
        'country',
        'coverage_area',
        'coverage_notes',
        'testimonial',
        'contact_reference',
        'show_in_valuation',
        'show_in_carousel',
        'is_featured',
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    public const CLIENT_TYPES = [
        'empresa' => 'Empresa',
        'desarrollador' => 'Desarrollador',
        'particular' => 'Particular',
        'institucion' => 'Institución',
        'industria' => 'Industria',
        'comercio' => 'Comercio',
        'notaria' => 'Notaría',
        'despacho' => 'Despacho',
        'gobierno' => 'Gobierno',
        'otro' => 'Otro',
    ];

    public const REPRESENTED_UNITS = [
        'valor_comercial_avaluos' => 'Valor Comercial Avalúos',
        'uva_abalkan' => 'UVA Abalkan',
        'ambas' => 'Ambas unidades',
        'no_aplica' => 'No aplica',
    ];

    public const SERVICE_OPTIONS = [
        'avaluo_inmobiliario_comercial' => 'Avalúo inmobiliario comercial',
        'estudio_de_valor' => 'Estudio de valor',
        'maquinaria_y_equipo' => 'Maquinaria y equipo',
        'valuacion_de_proyectos' => 'Valuación de proyectos',
        'asesoria_tecnica' => 'Asesoría técnica',
        'otro' => 'Otro',
    ];

    public static function ordered(): array
    {
        $instance = new static();

        return static::getDatabaseDriver()->statement(
            "SELECT vc.*, vu.name AS valuation_unit_name, vu.short_name AS valuation_unit_short_name
             FROM {$instance->table} vc
             LEFT JOIN valuation_units vu ON vu.id = vc.valuation_unit_id
             WHERE vc.deleted_at IS NULL
             ORDER BY vc.sort_order ASC, vc.id DESC"
        ) ?: [];
    }

    public static function active(): array
    {
        return array_values(array_filter(static::ordered(), function (array $client) {
            return (int) ($client['is_active'] ?? 0) === 1;
        }));
    }

    public static function featured(): array
    {
        return array_values(array_filter(static::active(), function (array $client) {
            return (int) ($client['is_featured'] ?? 0) === 1;
        }));
    }

    public static function carousel(): array
    {
        return array_values(array_filter(static::active(), function (array $client) {
            return (int) ($client['show_in_carousel'] ?? 0) === 1;
        }));
    }

    public static function visibleInValuation(): array
    {
        return array_values(array_filter(static::active(), function (array $client) {
            return (int) ($client['show_in_valuation'] ?? 0) === 1;
        }));
    }

    public static function findArray(int $id): ?array
    {
        $instance = new static();

        $rows = static::getDatabaseDriver()->statement(
            "SELECT vc.*, vu.name AS valuation_unit_name, vu.short_name AS valuation_unit_short_name
             FROM {$instance->table} vc
             LEFT JOIN valuation_units vu ON vu.id = vc.valuation_unit_id
             WHERE vc.id = ?
             LIMIT 1",
            [$id]
        ) ?: [];

        return $rows[0] ?? null;
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
        $clients = static::ordered();
        $max = 0;

        foreach ($clients as $client) {
            $max = max($max, (int) ($client['sort_order'] ?? 0));
        }

        return $max + 1;
    }

    public static function parseServices(mixed $value): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = explode(',', (string) ($value ?? ''));
        }

        $valid = array_keys(self::SERVICE_OPTIONS);

        return array_values(array_filter(array_map('trim', $items), function (string $item) use ($valid) {
            return in_array($item, $valid, true);
        }));
    }

    public static function serviceLabels(mixed $value): array
    {
        return array_values(array_map(function (string $service) {
            return self::SERVICE_OPTIONS[$service] ?? $service;
        }, self::parseServices($value)));
    }

    public static function clientTypeLabel(string $type): string
    {
        return self::CLIENT_TYPES[$type] ?? 'Otro';
    }

    public static function representedUnitLabel(string $unit): string
    {
        return self::REPRESENTED_UNITS[$unit] ?? 'No aplica';
    }
}
