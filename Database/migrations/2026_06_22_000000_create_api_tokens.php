<?php

use Whis\Database\DB;
use Whis\Database\Migrations\Migration;

return new class () implements Migration {
    public function up()
    {
        try {
            if (! $this->tableExists('api_tokens')) {
                DB::statement("
                    CREATE TABLE api_tokens (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                        tokenable_type VARCHAR(190) NULL,
                        tokenable_id BIGINT UNSIGNED NULL,

                        name VARCHAR(190) NOT NULL,
                        token_prefix VARCHAR(24) NOT NULL,
                        token_hash CHAR(64) NOT NULL,

                        abilities TEXT NULL,

                        expires_at DATETIME NULL,
                        last_used_at DATETIME NULL,
                        last_used_ip VARCHAR(45) NULL,
                        last_used_user_agent VARCHAR(255) NULL,

                        revoked_at DATETIME NULL,

                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

                        system TINYINT(1) NOT NULL DEFAULT 0,

                        UNIQUE KEY api_tokens_token_hash_unique (token_hash),
                        INDEX api_tokens_token_prefix_index (token_prefix),
                        INDEX api_tokens_tokenable_index (tokenable_type, tokenable_id),
                        INDEX api_tokens_revoked_expires_index (revoked_at, expires_at),
                        INDEX api_tokens_system_index (system)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Patches para tablas api_tokens ya existentes
            |--------------------------------------------------------------------------
            |
            | CREATE TABLE IF NOT EXISTS no modifica tablas viejas.
            | Por eso, si la tabla ya existía antes, agregamos columnas faltantes.
            |
            */

            if (! $this->columnExists('api_tokens', 'tokenable_type')) {
                DB::statement("
                    ALTER TABLE api_tokens
                    ADD COLUMN tokenable_type VARCHAR(190) NULL
                    AFTER id
                ");
            }

            if (! $this->columnExists('api_tokens', 'tokenable_id')) {
                DB::statement("
                    ALTER TABLE api_tokens
                    ADD COLUMN tokenable_id BIGINT UNSIGNED NULL
                    AFTER tokenable_type
                ");
            }

            if (! $this->columnExists('api_tokens', 'name')) {
                DB::statement("
                    ALTER TABLE api_tokens
                    ADD COLUMN name VARCHAR(190) NOT NULL DEFAULT 'API Token'
                    AFTER tokenable_id
                ");
            }

            if (! $this->columnExists('api_tokens', 'token_prefix')) {
                DB::statement("
                    ALTER TABLE api_tokens
                    ADD COLUMN token_prefix VARCHAR(24) NOT NULL DEFAULT ''
                    AFTER name
                ");
            }

            if (! $this->columnExists('api_tokens', 'token_hash')) {
                DB::statement("
                    ALTER TABLE api_tokens
                    ADD COLUMN token_hash CHAR(64) NOT NULL DEFAULT ''
                    AFTER token_prefix
                ");
            }

            if (! $this->columnExists('api_tokens', 'abilities')) {
                DB::statement("
                    ALTER TABLE api_tokens
                    ADD COLUMN abilities TEXT NULL
                    AFTER token_hash
                ");
            }

            if (! $this->columnExists('api_tokens', 'expires_at')) {
                DB::statement("
                    ALTER TABLE api_tokens
                    ADD COLUMN expires_at DATETIME NULL
                    AFTER abilities
                ");
            }

            if (! $this->columnExists('api_tokens', 'last_used_at')) {
                DB::statement("
                    ALTER TABLE api_tokens
                    ADD COLUMN last_used_at DATETIME NULL
                    AFTER expires_at
                ");
            }

            if (! $this->columnExists('api_tokens', 'last_used_ip')) {
                DB::statement("
                    ALTER TABLE api_tokens
                    ADD COLUMN last_used_ip VARCHAR(45) NULL
                    AFTER last_used_at
                ");
            }

            if (! $this->columnExists('api_tokens', 'last_used_user_agent')) {
                DB::statement("
                    ALTER TABLE api_tokens
                    ADD COLUMN last_used_user_agent VARCHAR(255) NULL
                    AFTER last_used_ip
                ");
            }

            if (! $this->columnExists('api_tokens', 'revoked_at')) {
                DB::statement("
                    ALTER TABLE api_tokens
                    ADD COLUMN revoked_at DATETIME NULL
                    AFTER last_used_user_agent
                ");
            }

            if (! $this->columnExists('api_tokens', 'created_at')) {
                DB::statement("
                    ALTER TABLE api_tokens
                    ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    AFTER revoked_at
                ");
            }

            if (! $this->columnExists('api_tokens', 'updated_at')) {
                DB::statement("
                    ALTER TABLE api_tokens
                    ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
                    AFTER created_at
                ");
            }

            if (! $this->columnExists('api_tokens', 'system')) {
                DB::statement("
                    ALTER TABLE api_tokens
                    ADD COLUMN system TINYINT(1) NOT NULL DEFAULT 0
                    AFTER updated_at
                ");
            }

            /*
            |--------------------------------------------------------------------------
            | Índices
            |--------------------------------------------------------------------------
            */

            if (! $this->indexExists('api_tokens', 'api_tokens_token_hash_unique')) {
                DB::statement("
                    ALTER TABLE api_tokens
                    ADD UNIQUE KEY api_tokens_token_hash_unique (token_hash)
                ");
            }

            if (! $this->indexExists('api_tokens', 'api_tokens_token_prefix_index')) {
                DB::statement("
                    ALTER TABLE api_tokens
                    ADD INDEX api_tokens_token_prefix_index (token_prefix)
                ");
            }

            if (! $this->indexExists('api_tokens', 'api_tokens_tokenable_index')) {
                DB::statement("
                    ALTER TABLE api_tokens
                    ADD INDEX api_tokens_tokenable_index (tokenable_type, tokenable_id)
                ");
            }

            if (! $this->indexExists('api_tokens', 'api_tokens_revoked_expires_index')) {
                DB::statement("
                    ALTER TABLE api_tokens
                    ADD INDEX api_tokens_revoked_expires_index (revoked_at, expires_at)
                ");
            }

            if (! $this->indexExists('api_tokens', 'api_tokens_system_index')) {
                DB::statement("
                    ALTER TABLE api_tokens
                    ADD INDEX api_tokens_system_index (system)
                ");
            }
        } catch (\PDOException $th) {
            throw $th;
        }
    }

    public function down()
    {
        try {
            DB::statement('DROP TABLE IF EXISTS api_tokens');
        } catch (\PDOException $th) {
            throw $th;
        }
    }

    private function tableExists(string $table): bool
    {
        $result = DB::statement(
            "SELECT COUNT(*) AS total
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table",
            [
                ':table' => $table,
            ]
        );

        return (int) ($result[0]['total'] ?? 0) > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $result = DB::statement(
            "SELECT COUNT(*) AS total
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND COLUMN_NAME = :column",
            [
                ':table'  => $table,
                ':column' => $column,
            ]
        );

        return (int) ($result[0]['total'] ?? 0) > 0;
    }

    private function indexExists(string $table, string $index): bool
    {
        $result = DB::statement(
            "SELECT COUNT(*) AS total
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND INDEX_NAME = :index_name",
            [
                ':table'      => $table,
                ':index_name' => $index,
            ]
        );

        return (int) ($result[0]['total'] ?? 0) > 0;
    }
};