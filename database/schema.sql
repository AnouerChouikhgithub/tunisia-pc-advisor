-- Tunisia PC Advisor — MySQL 8+ / MariaDB 10.4+ schema
-- Import with phpMyAdmin or: mysql -u root < database/schema.sql
-- Then import database/seed.sql.

CREATE DATABASE IF NOT EXISTS tunisia_pc_advisor
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE tunisia_pc_advisor;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS recommendation_results;
DROP TABLE IF EXISTS recommendation_sessions;
DROP TABLE IF EXISTS product_price_history;
DROP TABLE IF EXISTS activity_workloads;
DROP TABLE IF EXISTS activity_requirements;
DROP TABLE IF EXISTS workloads;
DROP TABLE IF EXISTS activities;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS retailers;
DROP TABLE IF EXISTS brands;
DROP TABLE IF EXISTS admin_users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE brands (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    logo_url VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_brands_name (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE retailers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    website_url VARCHAR(500) NOT NULL,
    logo_url VARCHAR(500) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_retailers_slug (slug)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand_id INT UNSIGNED NOT NULL,
    retailer_id INT UNSIGNED NOT NULL,
    model VARCHAR(180) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_category ENUM('laptop', 'desktop') NOT NULL,
    processor_brand VARCHAR(48) NULL,
    processor_model VARCHAR(180) NOT NULL,
    processor_generation VARCHAR(40) NULL,
    processor_cores TINYINT UNSIGNED NULL,
    ram_gb SMALLINT UNSIGNED NOT NULL,
    ram_type VARCHAR(32) NULL,
    ram_upgradeable ENUM('yes', 'no', 'unknown') NOT NULL DEFAULT 'unknown',
    storage_type VARCHAR(32) NOT NULL,
    storage_gb INT UNSIGNED NOT NULL,
    gpu_brand VARCHAR(48) NULL,
    gpu_model VARCHAR(180) NULL,
    gpu_type ENUM('dedicated', 'integrated', 'unknown') NOT NULL DEFAULT 'unknown',
    gpu_vram_gb TINYINT UNSIGNED NULL,
    screen_size DECIMAL(4, 1) NULL,
    screen_resolution VARCHAR(40) NULL,
    screen_panel VARCHAR(20) NULL,
    refresh_rate SMALLINT UNSIGNED NULL,
    operating_system VARCHAR(80) NULL,
    weight_kg DECIMAL(4, 2) NULL,
    battery VARCHAR(120) NULL,
    price_tnd DECIMAL(10, 3) NOT NULL,
    retailer_sku VARCHAR(120) NULL,
    product_url VARCHAR(1000) NOT NULL,
    product_url_hash CHAR(64) NOT NULL,
    image_url VARCHAR(1000) NULL,
    stock_status ENUM('in_stock', 'out_of_stock', 'limited', 'preorder', 'unknown') NOT NULL DEFAULT 'unknown',
    warranty_information VARCHAR(180) NULL,
    source_url VARCHAR(1000) NOT NULL,
    link_status ENUM('unchecked', 'ok', 'broken') NOT NULL DEFAULT 'unchecked',
    link_checked_at DATETIME NULL,
    verified_at DATETIME NULL,
    raw_specs_json JSON NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_url_hash (product_url_hash),
    UNIQUE KEY uq_products_retailer_sku (retailer_id, retailer_sku),
    KEY idx_products_brand (brand_id),
    KEY idx_products_retailer (retailer_id),
    KEY idx_products_category_price (product_category, price_tnd),
    KEY idx_products_active_stock (is_active, stock_status),
    KEY idx_products_ram (ram_gb),
    KEY idx_products_storage (storage_gb),
    KEY idx_products_gpu_type (gpu_type),
    KEY idx_products_verified (verified_at),
    CONSTRAINT fk_products_brand FOREIGN KEY (brand_id) REFERENCES brands (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_products_retailer FOREIGN KEY (retailer_id) REFERENCES retailers (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_products_price CHECK (price_tnd >= 0),
    CONSTRAINT chk_products_ram CHECK (ram_gb > 0),
    CONSTRAINT chk_products_storage CHECK (storage_gb > 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE activities (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(80) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NOT NULL,
    icon VARCHAR(48) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_activities_slug (slug)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE activity_requirements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    activity_id INT UNSIGNED NOT NULL,
    minimum_cpu_level DECIMAL(3, 1) NOT NULL,
    preferred_cpu_level DECIMAL(3, 1) NOT NULL,
    minimum_ram_gb SMALLINT UNSIGNED NOT NULL,
    preferred_ram_gb SMALLINT UNSIGNED NOT NULL,
    minimum_storage_gb INT UNSIGNED NOT NULL,
    preferred_storage_gb INT UNSIGNED NOT NULL,
    gpu_requirement ENUM('none', 'integrated_ok', 'dedicated_preferred', 'dedicated_required') NOT NULL,
    minimum_gpu_vram_gb TINYINT UNSIGNED NOT NULL DEFAULT 0,
    portability_importance ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    cpu_weight TINYINT UNSIGNED NOT NULL DEFAULT 20,
    ram_weight TINYINT UNSIGNED NOT NULL DEFAULT 15,
    gpu_weight TINYINT UNSIGNED NOT NULL DEFAULT 20,
    storage_weight TINYINT UNSIGNED NOT NULL DEFAULT 10,
    budget_weight TINYINT UNSIGNED NOT NULL DEFAULT 20,
    activity_weight TINYINT UNSIGNED NOT NULL DEFAULT 10,
    preferences_weight TINYINT UNSIGNED NOT NULL DEFAULT 5,
    notes VARCHAR(500) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_activity_requirements_activity (activity_id),
    CONSTRAINT fk_activity_requirements_activity FOREIGN KEY (activity_id) REFERENCES activities (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_activity_weights CHECK (
        cpu_weight + ram_weight + gpu_weight + storage_weight + budget_weight + activity_weight + preferences_weight = 100
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE workloads (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(80) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NOT NULL,
    minimum_cpu_level DECIMAL(3, 1) NOT NULL DEFAULT 2.0,
    minimum_ram_gb SMALLINT UNSIGNED NOT NULL DEFAULT 8,
    gpu_requirement ENUM('none', 'integrated_ok', 'dedicated_preferred', 'dedicated_required') NOT NULL DEFAULT 'none',
    minimum_gpu_vram_gb TINYINT UNSIGNED NOT NULL DEFAULT 0,
    minimum_storage_gb INT UNSIGNED NOT NULL DEFAULT 256,
    PRIMARY KEY (id),
    UNIQUE KEY uq_workloads_slug (slug)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE activity_workloads (
    activity_id INT UNSIGNED NOT NULL,
    workload_id INT UNSIGNED NOT NULL,
    relevance TINYINT UNSIGNED NOT NULL DEFAULT 2,
    PRIMARY KEY (activity_id, workload_id),
    KEY idx_activity_workloads_workload (workload_id),
    CONSTRAINT fk_activity_workloads_activity FOREIGN KEY (activity_id) REFERENCES activities (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_activity_workloads_workload FOREIGN KEY (workload_id) REFERENCES workloads (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_activity_workloads_relevance CHECK (relevance BETWEEN 1 AND 3)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE product_price_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    price_tnd DECIMAL(10, 3) NOT NULL,
    stock_status ENUM('in_stock', 'out_of_stock', 'limited', 'preorder', 'unknown') NOT NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_price_history_product_date (product_id, recorded_at),
    CONSTRAINT fk_price_history_product FOREIGN KEY (product_id) REFERENCES products (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_price_history_price CHECK (price_tnd >= 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE recommendation_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token CHAR(64) NOT NULL,
    activity_id INT UNSIGNED NOT NULL,
    activity_name VARCHAR(120) NOT NULL,
    workloads_json JSON NOT NULL,
    product_category ENUM('laptop', 'desktop', 'both') NOT NULL,
    budget_min DECIMAL(10, 3) NOT NULL DEFAULT 0,
    budget_max DECIMAL(10, 3) NOT NULL,
    min_ram_gb SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    min_storage_gb INT UNSIGNED NOT NULL DEFAULT 0,
    dedicated_gpu ENUM('yes', 'no', 'any') NOT NULL DEFAULT 'any',
    preferred_brands_json JSON NULL,
    portability_importance ENUM('low', 'medium', 'high', 'any') NOT NULL DEFAULT 'any',
    performance_importance ENUM('low', 'medium', 'high', 'any') NOT NULL DEFAULT 'any',
    battery_importance ENUM('low', 'medium', 'high', 'any') NOT NULL DEFAULT 'any',
    result_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    candidate_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recommendation_sessions_token (token),
    KEY idx_recommendation_sessions_created (created_at),
    CONSTRAINT fk_recommendation_sessions_activity FOREIGN KEY (activity_id) REFERENCES activities (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE recommendation_results (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id BIGINT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    compatibility_score DECIMAL(5, 2) NOT NULL,
    explanation TEXT NOT NULL,
    rank_position TINYINT UNSIGNED NOT NULL,
    score_breakdown_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recommendation_results_product (session_id, product_id),
    KEY idx_recommendation_results_rank (session_id, rank_position),
    CONSTRAINT fk_recommendation_results_session FOREIGN KEY (session_id) REFERENCES recommendation_sessions (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_recommendation_results_product FOREIGN KEY (product_id) REFERENCES products (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_recommendation_rank CHECK (rank_position BETWEEN 1 AND 5),
    CONSTRAINT chk_recommendation_score CHECK (compatibility_score BETWEEN 0 AND 100)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE admin_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(60) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_users_username (username)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
