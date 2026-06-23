<?php

use Whis\Database\DB;
use Whis\Database\Migrations\Migration;

return new class () implements Migration {
    public function up()
    {
        try {
            DB::statement("
                CREATE TABLE clients (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                    name VARCHAR(255) NOT NULL,
                    slug VARCHAR(180) UNIQUE,

                    url TEXT,
                    logo_url TEXT,
                    logo_alt TEXT,
                    initials VARCHAR(10),

                    description TEXT,
                    industry VARCHAR(160),

                    is_featured TINYINT(1) DEFAULT 0,
                    is_active TINYINT(1) DEFAULT 1,
                    sort_order INT UNSIGNED DEFAULT 0,

                    created_by INT(11) NULL,
                    updated_by INT(11) NULL,
                    deleted_by INT(11) NULL,
                    deleted_at DATETIME NULL,

                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                    INDEX idx_clients_name (name),
                    INDEX idx_clients_slug (slug),
                    INDEX idx_clients_industry (industry),
                    INDEX idx_clients_featured (is_featured),
                    INDEX idx_clients_active (is_active),
                    INDEX idx_clients_order (sort_order),
                    INDEX idx_clients_deleted_at (deleted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\PDOException $th) {
            throw $th;
        }
    }

    public function down()
    {
        try {
            DB::statement('DROP TABLE IF EXISTS clients');
        } catch (\PDOException $th) {
            throw $th;
        }
    }
};