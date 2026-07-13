<?php
namespace App\Models;

use Whis\Database\Model;

class ValuationMessage extends Model
{
    protected ?string $table = 'valuation_messages';

    protected string $primaryKey = 'id';

    protected array $fillable = [
        'name',
        'email',
        'phone',
        'valuation_type',
        'valuation_type_label',
        'message',
        'source_page',
        'source_url',
        'referrer_url',
        'status',
        'priority',
        'assigned_to',
        'ip_address',
        'user_agent',
        'read_at',
        'answered_at',
        'archived_at',
        'created_at',
        'updated_at',
    ];

    public const STATUS_NEW         = 'new';
    public const STATUS_READ        = 'read';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_ANSWERED    = 'answered';
    public const STATUS_ARCHIVED    = 'archived';
    public const STATUS_SPAM        = 'spam';

    public const PRIORITY_LOW    = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH   = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const VALUATION_TYPES = [
        'avaluo-inmobiliario-comercial' => 'Avalúo inmobiliario comercial',
        'estudio-de-valor'              => 'Estudio de valor',
        'maquinaria-y-equipo'           => 'Avalúo de maquinaria y equipo',
        'valuacion-de-proyectos'        => 'Valuación de proyectos',
    ];

    public static function newest(): array
    {
        return static::forAdmin();
    }

    public static function forAdmin(): array
    {
        $instance = new static();

        return static::getDatabaseDriver()->statement(
            "SELECT *
             FROM {$instance->table}
             ORDER BY created_at DESC, id DESC"
        ) ?: [];
    }

    public static function findArray(int $id): ?array
    {
        $message = static::find($id);

        return $message ? $message->toArray() : null;
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
            fn ($column) => "{$column} = ?",
            $columns
        ));

        $values[] = $id;

        static::getDatabaseDriver()->statement(
            "UPDATE {$instance->table}
             SET {$set}
             WHERE {$instance->primaryKey} = ?",
            $values
        );

        return true;
    }

    public static function deleteById(int $id): bool
    {
        $instance = new static();

        static::getDatabaseDriver()->statement(
            "DELETE FROM {$instance->table}
             WHERE {$instance->primaryKey} = ?",
            [$id]
        );

        return true;
    }

    public static function stats(): array
    {
        $messages = static::forAdmin();

        $stats = [
            'total'       => count($messages),
            'new'         => 0,
            'read'        => 0,
            'in_progress' => 0,
            'answered'    => 0,
            'archived'    => 0,
            'spam'        => 0,
            'urgent'      => 0,
            'types'       => [],
        ];

        foreach ($messages as $message) {
            $status = (string) ($message['status'] ?? self::STATUS_NEW);

            if (array_key_exists($status, $stats)) {
                $stats[$status]++;
            }

            if (($message['priority'] ?? '') === self::PRIORITY_URGENT) {
                $stats['urgent']++;
            }

            $type = (string) ($message['valuation_type'] ?? '');

            if ($type !== '') {
                $stats['types'][$type] = ($stats['types'][$type] ?? 0) + 1;
            }
        }

        return $stats;
    }

    public static function allowedStatuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_READ,
            self::STATUS_IN_PROGRESS,
            self::STATUS_ANSWERED,
            self::STATUS_ARCHIVED,
            self::STATUS_SPAM,
        ];
    }

    public static function allowedPriorities(): array
    {
        return [
            self::PRIORITY_LOW,
            self::PRIORITY_NORMAL,
            self::PRIORITY_HIGH,
            self::PRIORITY_URGENT,
        ];
    }

    public static function valuationTypeLabel(string $type): string
    {
        return self::VALUATION_TYPES[$type] ?? 'Solicitud de valuación';
    }
}
