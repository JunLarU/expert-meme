<?php

use Whis\Database\DB;
use Whis\Database\Migrations\Migration;

return new class () implements Migration {
    public function up()
    {
        try {
            DB::statement("
                CREATE TABLE projects (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                    slug VARCHAR(180) UNIQUE,
                    status ENUM('draft', 'published', 'hidden', 'archived') DEFAULT 'draft',

                    title VARCHAR(255) NOT NULL,
                    subtitle TEXT,
                    brief TEXT,
                    summary TEXT,
                    description LONGTEXT,

                    category VARCHAR(120),
                    category_badge VARCHAR(120),
                    listing_number VARCHAR(20),

                    href TEXT,

                    cover_image_url TEXT,
                    cover_image_alt TEXT,
                    cover_mobile_url TEXT,

                    hero_eyebrow VARCHAR(180),
                    hero_title VARCHAR(255),
                    hero_copy TEXT,
                    hero_background_url TEXT,
                    hero_button_label VARCHAR(180),
                    hero_button_url TEXT,

                    location_display VARCHAR(255),
                    city VARCHAR(120),
                    state VARCHAR(120),
                    country VARCHAR(120) DEFAULT 'México',
                    project_year SMALLINT UNSIGNED,

                    client_name VARCHAR(255),
                    client_type VARCHAR(160),
                    service VARCHAR(255),
                    specialty VARCHAR(255),
                    material_system VARCHAR(255),

                    weight_label VARCHAR(120),
                    area_label VARCHAR(120),
                    duration_label VARCHAR(120),
                    scope_label TEXT,

                    overview_eyebrow VARCHAR(180),
                    overview_title VARCHAR(255),
                    overview_body LONGTEXT,

                    result_eyebrow VARCHAR(180),
                    result_title VARCHAR(255),
                    result_body LONGTEXT,
                    result_button_label VARCHAR(180),
                    result_button_url TEXT,

                    is_featured TINYINT(1) DEFAULT 0,
                    is_newest TINYINT(1) DEFAULT 0,
                    show_in_home TINYINT(1) DEFAULT 0,
                    show_in_projects TINYINT(1) DEFAULT 1,
                    show_on_map TINYINT(1) DEFAULT 1,

                    sort_order INT UNSIGNED DEFAULT 0,

                    seo_title VARCHAR(255),
                    seo_description TEXT,

                    created_by INT(11) NULL,
                    updated_by INT(11) NULL,
                    deleted_by INT(11) NULL,
                    deleted_at DATETIME NULL,

                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                    INDEX idx_projects_status (status),
                    INDEX idx_projects_slug (slug),
                    INDEX idx_projects_state (state),
                    INDEX idx_projects_city (city),
                    INDEX idx_projects_year (project_year),
                    INDEX idx_projects_featured (is_featured),
                    INDEX idx_projects_newest (is_newest),
                    INDEX idx_projects_home (show_in_home),
                    INDEX idx_projects_map (show_on_map),
                    INDEX idx_projects_order (sort_order),
                    INDEX idx_projects_deleted_at (deleted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            DB::statement("
                CREATE TABLE project_tags (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                    project_id BIGINT UNSIGNED NOT NULL,

                    name VARCHAR(120) NOT NULL,
                    slug VARCHAR(140),
                    type ENUM('tag', 'category', 'service', 'specialty', 'material') DEFAULT 'tag',
                    sort_order INT UNSIGNED DEFAULT 0,

                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                    INDEX idx_project_tags_project (project_id),
                    INDEX idx_project_tags_type (type),
                    INDEX idx_project_tags_slug (slug),

                    CONSTRAINT fk_project_tags_project
                        FOREIGN KEY (project_id)
                        REFERENCES projects(id)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            DB::statement("
                CREATE TABLE project_facts (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                    project_id BIGINT UNSIGNED NOT NULL,

                    label VARCHAR(160) NOT NULL,
                    value TEXT NOT NULL,
                    icon VARCHAR(120),
                    sort_order INT UNSIGNED DEFAULT 0,

                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                    INDEX idx_project_facts_project (project_id),
                    INDEX idx_project_facts_order (sort_order),

                    CONSTRAINT fk_project_facts_project
                        FOREIGN KEY (project_id)
                        REFERENCES projects(id)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            DB::statement("
                CREATE TABLE project_media (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                    project_id BIGINT UNSIGNED NOT NULL,

                    media_type ENUM('image', 'video') DEFAULT 'image',
                    display_area ENUM('gallery', 'hero', 'cover', 'related') DEFAULT 'gallery',

                    title VARCHAR(255),
                    description TEXT,

                    file_url TEXT NOT NULL,
                    mobile_url TEXT,
                    poster_url TEXT,

                    alt_text TEXT,
                    aria_label VARCHAR(255),

                    width INT UNSIGNED,
                    height INT UNSIGNED,

                    video_preload VARCHAR(40) DEFAULT 'none',
                    video_controls TINYINT(1) DEFAULT 1,
                    video_autoplay TINYINT(1) DEFAULT 0,
                    video_muted TINYINT(1) DEFAULT 0,
                    video_loop TINYINT(1) DEFAULT 0,
                    video_playsinline TINYINT(1) DEFAULT 1,

                    is_featured TINYINT(1) DEFAULT 0,
                    sort_order INT UNSIGNED DEFAULT 0,

                    created_by INT(11) NULL,
                    updated_by INT(11) NULL,

                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                    INDEX idx_project_media_project (project_id),
                    INDEX idx_project_media_type (media_type),
                    INDEX idx_project_media_area (display_area),
                    INDEX idx_project_media_order (sort_order),

                    CONSTRAINT fk_project_media_project
                        FOREIGN KEY (project_id)
                        REFERENCES projects(id)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            DB::statement("
                CREATE TABLE project_scope_items (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                    project_id BIGINT UNSIGNED NOT NULL,

                    number_label VARCHAR(20),
                    title VARCHAR(255) NOT NULL,
                    description TEXT,
                    icon VARCHAR(120),
                    sort_order INT UNSIGNED DEFAULT 0,

                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                    INDEX idx_project_scope_project (project_id),
                    INDEX idx_project_scope_order (sort_order),

                    CONSTRAINT fk_project_scope_project
                        FOREIGN KEY (project_id)
                        REFERENCES projects(id)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            DB::statement("
                CREATE TABLE project_result_stats (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                    project_id BIGINT UNSIGNED NOT NULL,

                    value VARCHAR(80) NOT NULL,
                    label VARCHAR(180) NOT NULL,
                    description TEXT,
                    sort_order INT UNSIGNED DEFAULT 0,

                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                    INDEX idx_project_result_stats_project (project_id),
                    INDEX idx_project_result_stats_order (sort_order),

                    CONSTRAINT fk_project_result_stats_project
                        FOREIGN KEY (project_id)
                        REFERENCES projects(id)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            DB::statement("
                CREATE TABLE map_markers (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                    project_id BIGINT UNSIGNED NULL,

                    type ENUM('project', 'office', 'workshop') DEFAULT 'project',

                    title VARCHAR(255) NOT NULL,
                    kind VARCHAR(180),
                    location VARCHAR(255),
                    city VARCHAR(120),
                    state VARCHAR(120) NOT NULL,
                    country VARCHAR(120) DEFAULT 'México',

                    latitude DECIMAL(12, 8) NULL,
                    longitude DECIMAL(12, 8) NULL,

                    summary TEXT,
                    href TEXT,

                    image_url TEXT,
                    image_alt TEXT,

                    marker_color VARCHAR(40),
                    is_active TINYINT(1) DEFAULT 1,
                    sort_order INT UNSIGNED DEFAULT 0,

                    created_by INT(11) NULL,
                    updated_by INT(11) NULL,

                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                    INDEX idx_map_markers_project (project_id),
                    INDEX idx_map_markers_type (type),
                    INDEX idx_map_markers_state (state),
                    INDEX idx_map_markers_city (city),
                    INDEX idx_map_markers_active (is_active),
                    INDEX idx_map_markers_order (sort_order),
                    INDEX idx_map_markers_lat_lng (latitude, longitude),

                    CONSTRAINT fk_map_markers_project
                        FOREIGN KEY (project_id)
                        REFERENCES projects(id)
                        ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\PDOException $th) {
            throw $th;
        }
    }

    public function down()
    {
        try {
            DB::statement('DROP TABLE IF EXISTS map_markers');
            DB::statement('DROP TABLE IF EXISTS project_result_stats');
            DB::statement('DROP TABLE IF EXISTS project_scope_items');
            DB::statement('DROP TABLE IF EXISTS project_media');
            DB::statement('DROP TABLE IF EXISTS project_facts');
            DB::statement('DROP TABLE IF EXISTS project_tags');
            DB::statement('DROP TABLE IF EXISTS projects');
        } catch (\PDOException $th) {
            throw $th;
        }
    }
};