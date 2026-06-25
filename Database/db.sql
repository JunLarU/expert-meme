SET FOREIGN_KEY_CHECKS = 0;

-- ==========================================================
-- 1. users
-- ==========================================================

CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255),
    role ENUM('admin', 'manager') DEFAULT 'manager',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================================
-- 2. api_tokens
-- ==========================================================

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================================
-- 3. home_jumbotron_slides
-- ==========================================================

CREATE TABLE IF NOT EXISTS home_jumbotron_slides (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================================
-- 4. projects
-- ==========================================================

CREATE TABLE IF NOT EXISTS projects (
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
    is_home_featured TINYINT(1) DEFAULT 0,
    show_in_home TINYINT(1) DEFAULT 1,
    show_in_projects TINYINT(1) DEFAULT 1,
    show_on_map TINYINT(1) DEFAULT 1,

    map_type ENUM('project', 'office', 'workshop') DEFAULT 'project',
    map_lat DECIMAL(11,8) NULL,
    map_lng DECIMAL(11,8) NULL,
    map_state VARCHAR(120) NULL,
    map_title VARCHAR(255) NULL,
    map_kind VARCHAR(160) NULL,
    map_location VARCHAR(255) NULL,
    map_summary TEXT NULL,
    map_image_url TEXT NULL,
    map_image_alt VARCHAR(255) NULL,

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
    INDEX idx_projects_year (project_year),
    INDEX idx_projects_featured (is_featured),
    INDEX idx_projects_home_featured (is_home_featured),
    INDEX idx_projects_home (show_in_home),
    INDEX idx_projects_map (show_on_map),
    INDEX idx_projects_map_geo (map_lat, map_lng),
    INDEX idx_projects_order (sort_order),
    INDEX idx_projects_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================================
-- 5. project_tags
-- ==========================================================

CREATE TABLE IF NOT EXISTS project_tags (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================================
-- 6. project_facts
-- ==========================================================

CREATE TABLE IF NOT EXISTS project_facts (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================================
-- 7. project_media
-- ==========================================================

CREATE TABLE IF NOT EXISTS project_media (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================================
-- 8. project_scope_items
-- ==========================================================

CREATE TABLE IF NOT EXISTS project_scope_items (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================================
-- 9. project_result_stats
-- ==========================================================

CREATE TABLE IF NOT EXISTS project_result_stats (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================================
-- 10. map_markers
-- ==========================================================

CREATE TABLE IF NOT EXISTS map_markers (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================================
-- 11. messages
-- ==========================================================

CREATE TABLE IF NOT EXISTS messages (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================================
-- 12. clients
-- ==========================================================

CREATE TABLE IF NOT EXISTS clients (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================================
-- 13. audit_logs
-- ==========================================================

CREATE TABLE IF NOT EXISTS audit_logs (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================================
-- 14. audit_log_changes
-- ==========================================================

CREATE TABLE IF NOT EXISTS audit_log_changes (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS office_workshops (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    slug VARCHAR(180) NOT NULL UNIQUE,
    type ENUM('office', 'workshop') NOT NULL DEFAULT 'office',
    status ENUM('draft', 'published', 'hidden', 'archived') NOT NULL DEFAULT 'draft',

    title VARCHAR(255) NOT NULL,
    summary TEXT NULL,
    description TEXT NULL,

    address TEXT NULL,
    city VARCHAR(160) NULL,
    state VARCHAR(120) NULL,
    country VARCHAR(80) NOT NULL DEFAULT 'México',
    postal_code VARCHAR(20) NULL,

    contact_name VARCHAR(180) NULL,
    phone VARCHAR(60) NULL,
    email VARCHAR(180) NULL,
    whatsapp VARCHAR(60) NULL,
    opening_hours VARCHAR(255) NULL,
    google_maps_url TEXT NULL,

    show_on_map TINYINT(1) NOT NULL DEFAULT 1,
    map_lat DECIMAL(10,8) NULL,
    map_lng DECIMAL(11,8) NULL,
    map_title VARCHAR(255) NULL,
    map_kind VARCHAR(120) NULL,
    map_location VARCHAR(255) NULL,
    map_summary TEXT NULL,
    map_image_url TEXT NULL,
    map_image_alt VARCHAR(255) NULL,

    sort_order INT UNSIGNED NOT NULL DEFAULT 0,

    created_by INT(11) NULL,
    updated_by INT(11) NULL,
    deleted_by INT(11) NULL,
    deleted_at DATETIME NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_office_workshops_type (type),
    INDEX idx_office_workshops_status (status),
    INDEX idx_office_workshops_show_on_map (show_on_map),
    INDEX idx_office_workshops_state (state),
    INDEX idx_office_workshops_sort (sort_order, id),
    INDEX idx_office_workshops_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;