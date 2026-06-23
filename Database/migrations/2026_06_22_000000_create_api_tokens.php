<?php

use Whis\Database\DB;
use Whis\Database\Migrations\Migration;

return new class () implements Migration {
    public function up()
    {
        try {
            DB::statement("
                CREATE TABLE IF NOT EXISTS api_tokens (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                    tokenable_type VARCHAR(190) NULL,
                    tokenable_id BIGINT UNSIGNED NULL,

                    name VARCHAR(190) NOT NULL,
                    token_prefix VARCHAR(24) NOT NULL,
                    token_hash CHAR(64) NOT NULL,

                    abilities TEXT NULL,

                    expires_at DATETIME NULL,
                    last_used_at DATETIME NULL,
                    revoked_at DATETIME NULL,

                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

                    UNIQUE KEY api_tokens_token_hash_unique (token_hash),
                    INDEX api_tokens_token_prefix_index (token_prefix),
                    INDEX api_tokens_tokenable_index (tokenable_type, tokenable_id),
                    INDEX api_tokens_revoked_expires_index (revoked_at, expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\PDOException $th) {
            exit;
        }
    }

    public function down()
    {
        try {
            DB::statement('DROP TABLE IF EXISTS api_tokens');
        } catch (\PDOException $th) {
            exit;
        }
    }
};