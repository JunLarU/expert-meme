<?php

namespace App\Models;

use Whis\Auth\Authenticatable;

class User extends Authenticatable
{
    protected ?string $table = "users";

    protected string $primaryKey = "id";

    protected array $hidden = ["password"];

    protected array $fillable = [
        "name",
        "email",
        "password",
        "role",
        "created_at",
        "updated_at",
    ];

    public const ROLE_ADMIN   = "admin";
    public const ROLE_MANAGER = "manager";
    public const ROLE_USER    = "user";

    public static function allowedAdminRoles(): array
    {
        return [
            self::ROLE_ADMIN,
            self::ROLE_MANAGER,
        ];
    }

    public static function forAdmin(): array
    {
        $instance = new static();

        return static::getDatabaseDriver()->statement(
            "SELECT id, name, email, role, created_at, updated_at
             FROM {$instance->table}
             WHERE role IN ('admin', 'manager')
             ORDER BY
                CASE role
                    WHEN 'admin' THEN 1
                    WHEN 'manager' THEN 2
                    ELSE 3
                END,
                name ASC,
                id ASC"
        ) ?: [];
    }

    public static function findArray(int $id): ?array
    {
        $user = static::find($id);

        return $user ? $user->toArray() : null;
    }

    public static function findAdminArray(int $id): ?array
    {
        $user = static::findArray($id);

        if (! $user) {
            return null;
        }

        if (! in_array($user['role'] ?? '', static::allowedAdminRoles(), true)) {
            return null;
        }

        return $user;
    }

    public static function emailExists(string $email, ?int $exceptId = null): bool
    {
        $instance = new static();

        $email = trim(strtolower($email));

        if ($email === '') {
            return false;
        }

        $params = [$email];
        $sql = "SELECT id
                FROM {$instance->table}
                WHERE LOWER(email) = ?
                LIMIT 1";

        if ($exceptId !== null) {
            $sql = "SELECT id
                    FROM {$instance->table}
                    WHERE LOWER(email) = ?
                    AND id <> ?
                    LIMIT 1";

            $params[] = $exceptId;
        }

        $rows = static::getDatabaseDriver()->statement($sql, $params) ?: [];

        return ! empty($rows);
    }

    public static function updateById(int $id, array $attributes): bool
    {
        $instance = new static();

        $columns = [];
        $values = [];

        foreach ($attributes as $key => $value) {
            if (! in_array($key, $instance->fillable, true)) {
                continue;
            }

            if ($key === $instance->primaryKey || $key === "created_at") {
                continue;
            }

            $columns[] = $key;
            $values[] = $value;
        }

        if (! in_array("updated_at", $columns, true)) {
            $columns[] = "updated_at";
            $values[] = date("Y-m-d H:i:s");
        }

        if (empty($columns)) {
            return true;
        }

        $set = implode(", ", array_map(
            fn(string $column) => "{$column} = ?",
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
        $users = static::forAdmin();

        $stats = [
            "total"   => count($users),
            "admin"   => 0,
            "manager" => 0,
        ];

        foreach ($users as $user) {
            $role = $user["role"] ?? "";

            if ($role === self::ROLE_ADMIN) {
                $stats["admin"]++;
            }

            if ($role === self::ROLE_MANAGER) {
                $stats["manager"]++;
            }
        }

        return $stats;
    }
}