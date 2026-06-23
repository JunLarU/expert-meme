<?php

use Whis\Database\DB;
use Whis\Database\Migrations\Migration;

return new class () implements Migration {
    public function up()
    {
        try {
            DB::statement("
                CREATE TABLE home_jumbotron_slides (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                    page VARCHAR(100) DEFAULT 'home',
                    slug VARCHAR(180) UNIQUE,

                    status ENUM('draft', 'published', 'hidden') DEFAULT 'draft',
                    sort_order INT UNSIGNED DEFAULT 0,

                    slide_class VARCHAR(255),
                    content_class VARCHAR(255),
                    button_class VARCHAR(255),

                    media_mode ENUM('none', 'background', 'inline_image', 'inline_video', 'mixed') DEFAULT 'none',
                    background_type ENUM('none', 'image', 'video') DEFAULT 'none',

                    background_url TEXT,
                    background_mobile_url TEXT,
                    background_alt TEXT,

                    video_url TEXT,
                    video_mobile_url TEXT,
                    video_poster_url TEXT,
                    video_aria_label VARCHAR(255),
                    video_preload VARCHAR(40) DEFAULT 'none',
                    video_controls TINYINT(1) DEFAULT 0,
                    video_autoplay TINYINT(1) DEFAULT 0,
                    video_muted TINYINT(1) DEFAULT 1,
                    video_loop TINYINT(1) DEFAULT 0,
                    video_playsinline TINYINT(1) DEFAULT 1,

                    image_url TEXT,
                    image_mobile_url TEXT,
                    image_alt TEXT,
                    image_width INT UNSIGNED,
                    image_height INT UNSIGNED,

                    eyebrow VARCHAR(180),
                    title VARCHAR(255),
                    subtitle TEXT,
                    body TEXT,

                    button_label VARCHAR(180),
                    button_url TEXT,
                    button_title VARCHAR(255),
                    button_aria_label VARCHAR(255),
                    button_target VARCHAR(40),
                    button_rel VARCHAR(120),

                    content_position ENUM('default', 'center', 'top', 'bottom', 'left', 'right') DEFAULT 'center',
                    overlay_enabled TINYINT(1) DEFAULT 1,
                    overlay_variant VARCHAR(100),

                    custom_style TEXT,
                    extra_attributes LONGTEXT,

                    is_critical TINYINT(1) DEFAULT 0,
                    starts_at DATETIME NULL,
                    ends_at DATETIME NULL,

                    created_by INT(11) NULL,
                    updated_by INT(11) NULL,

                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                    INDEX idx_jumbotron_page (page),
                    INDEX idx_jumbotron_status (status),
                    INDEX idx_jumbotron_order (sort_order),
                    INDEX idx_jumbotron_dates (starts_at, ends_at),
                    INDEX idx_jumbotron_created_by (created_by),
                    INDEX idx_jumbotron_updated_by (updated_by)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\PDOException $th) {
            throw $th;
        }
    }

    public function down()
    {
        try {
            DB::statement('DROP TABLE IF EXISTS home_jumbotron_slides');
        } catch (\PDOException $th) {
            throw $th;
        }
    }
};