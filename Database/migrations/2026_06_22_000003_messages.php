<?php

use Whis\Database\DB;
use Whis\Database\Migrations\Migration;

return new class () implements Migration {
    public function up()
    {
        try {
            DB::statement("
                CREATE TABLE messages (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                    name VARCHAR(180) NOT NULL,
                    company VARCHAR(180),
                    email VARCHAR(180) NOT NULL,
                    phone VARCHAR(80),

                    service VARCHAR(180),
                    subject VARCHAR(255),
                    project_location VARCHAR(255),

                    message LONGTEXT NOT NULL,

                    source_page VARCHAR(120) DEFAULT 'contact',
                    source_url TEXT,
                    referrer_url TEXT,

                    status ENUM('new', 'read', 'in_progress', 'answered', 'archived', 'spam') DEFAULT 'new',
                    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',

                    assigned_to INT(11) NULL,

                    ip_address VARCHAR(45),
                    user_agent TEXT,

                    read_at DATETIME NULL,
                    answered_at DATETIME NULL,
                    archived_at DATETIME NULL,

                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                    INDEX idx_messages_email (email),
                    INDEX idx_messages_status (status),
                    INDEX idx_messages_priority (priority),
                    INDEX idx_messages_assigned_to (assigned_to),
                    INDEX idx_messages_created_at (created_at),
                    INDEX idx_messages_read_at (read_at),
                    INDEX idx_messages_answered_at (answered_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\PDOException $th) {
            throw $th;
        }
    }

    public function down()
    {
        try {
            DB::statement('DROP TABLE IF EXISTS messages');
        } catch (\PDOException $th) {
            throw $th;
        }
    }
};