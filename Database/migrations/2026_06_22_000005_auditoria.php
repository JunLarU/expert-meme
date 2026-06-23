<?php

use Whis\Database\DB;
use Whis\Database\Migrations\Migration;

return new class () implements Migration {
    public function up()
    {
        try {
            DB::statement("
                CREATE TABLE audit_logs (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                    table_name VARCHAR(120) NOT NULL,
                    record_id BIGINT UNSIGNED NULL,

                    action ENUM('created', 'updated', 'deleted', 'restored', 'status_changed') NOT NULL,

                    actor_user_id INT(11) NULL,
                    actor_name VARCHAR(255),
                    actor_email VARCHAR(255),

                    record_label VARCHAR(255),

                    old_values LONGTEXT,
                    new_values LONGTEXT,

                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    request_method VARCHAR(20),
                    request_url TEXT,

                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                    INDEX idx_audit_table_record (table_name, record_id),
                    INDEX idx_audit_action (action),
                    INDEX idx_audit_actor (actor_user_id),
                    INDEX idx_audit_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            DB::statement("
                CREATE TABLE audit_log_changes (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                    audit_log_id BIGINT UNSIGNED NOT NULL,

                    field_name VARCHAR(180) NOT NULL,
                    old_value LONGTEXT,
                    new_value LONGTEXT,

                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                    INDEX idx_audit_changes_log (audit_log_id),
                    INDEX idx_audit_changes_field (field_name),

                    CONSTRAINT fk_audit_changes_log
                        FOREIGN KEY (audit_log_id)
                        REFERENCES audit_logs(id)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\PDOException $th) {
            throw $th;
        }
    }

    public function down()
    {
        try {
            DB::statement('DROP TABLE IF EXISTS audit_log_changes');
            DB::statement('DROP TABLE IF EXISTS audit_logs');
        } catch (\PDOException $th) {
            throw $th;
        }
    }
};