SET NAMES utf8mb4;
SET time_zone = '-03:00';

CREATE TABLE IF NOT EXISTS admin_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('administrator','auditor') NOT NULL DEFAULT 'auditor',
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finalists (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(190) NOT NULL UNIQUE,
    participant_name VARCHAR(190) NOT NULL,
    project_title VARCHAR(190) NOT NULL,
    instagram_url VARCHAR(500) NOT NULL,
    instagram_embed_url VARCHAR(500) NULL,
    fallback_image_url VARCHAR(500) NULL,
    active TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_finalists_active (active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_chain_state (
    id TINYINT UNSIGNED PRIMARY KEY,
    last_event_id BIGINT UNSIGNED NULL,
    last_hash CHAR(64) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin;

CREATE TABLE IF NOT EXISTS audit_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    actor_type ENUM('visitor','admin','system') NOT NULL DEFAULT 'visitor',
    actor_id BIGINT UNSIGNED NULL,
    visitor_hash CHAR(64) NOT NULL,
    device_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    user_agent_hash CHAR(64) NOT NULL,
    country_code CHAR(2) NOT NULL DEFAULT 'XX',
    request_method VARCHAR(10) NOT NULL,
    request_path VARCHAR(500) NOT NULL,
    risk_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    metadata_json JSON NOT NULL,
    previous_hash CHAR(64) NOT NULL,
    entry_hash CHAR(64) NOT NULL UNIQUE,
    created_at DATETIME(6) NOT NULL,
    INDEX idx_audit_created (created_at),
    INDEX idx_audit_event (event_type, created_at),
    INDEX idx_audit_ip (ip_hash, created_at),
    INDEX idx_audit_device (device_hash, created_at),
    INDEX idx_audit_risk (risk_score, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS votes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    finalist_id BIGINT UNSIGNED NOT NULL,
    audit_event_id BIGINT UNSIGNED NULL,
    receipt_code VARCHAR(32) NOT NULL UNIQUE,
    device_hash CHAR(64) NOT NULL UNIQUE,
    visitor_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    user_agent_hash CHAR(64) NOT NULL,
    country_code CHAR(2) NOT NULL,
    risk_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    risk_signals_json JSON NOT NULL,
    status ENUM('valid','review','invalid') NOT NULL DEFAULT 'review',
    review_notes TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    confirmed_at DATETIME NOT NULL,
    CONSTRAINT fk_votes_finalist FOREIGN KEY (finalist_id) REFERENCES finalists(id),
    CONSTRAINT fk_votes_audit FOREIGN KEY (audit_event_id) REFERENCES audit_events(id),
    CONSTRAINT fk_votes_reviewer FOREIGN KEY (reviewed_by) REFERENCES admin_users(id),
    INDEX idx_votes_status (status, confirmed_at),
    INDEX idx_votes_finalist (finalist_id, status),
    INDEX idx_votes_ip (ip_hash, confirmed_at),
    INDEX idx_votes_visitor (visitor_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    succeeded TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_auth_ip (ip_hash, created_at),
    INDEX idx_auth_email (email_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin;

CREATE TABLE IF NOT EXISTS registrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_code VARCHAR(32) NOT NULL UNIQUE,
    email_hash CHAR(64) NOT NULL,
    encrypted_payload LONGTEXT NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    country_code CHAR(2) NOT NULL DEFAULT 'XX',
    status ENUM('received','eligible','ineligible','finalist','winner','withdrawn') NOT NULL DEFAULT 'received',
    consented_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_registration_email (email_hash),
    INDEX idx_registration_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registration_files (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration_id BIGINT UNSIGNED NOT NULL,
    file_kind ENUM('project_photo','invoice') NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(100) NOT NULL UNIQUE,
    mime_type VARCHAR(100) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_files_registration FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
    INDEX idx_files_registration (registration_id, file_kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO audit_chain_state (id, last_event_id, last_hash)
VALUES (1, NULL, REPEAT('0', 64))
ON DUPLICATE KEY UPDATE id = id;
