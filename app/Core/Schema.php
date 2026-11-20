<?php

declare(strict_types=1);

namespace App\Core;

final class Schema
{
    private static bool $finalistImagesReady = false;

    public static function ensureFinalistImages(): void
    {
        if (self::$finalistImagesReady) {
            return;
        }
        if (Settings::get('schema_finalist_images_v1', '0') === '1') {
            self::$finalistImagesReady = true;
            return;
        }

        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS finalist_images ('
            . 'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
            . 'finalist_id BIGINT UNSIGNED NOT NULL,'
            . 'original_name VARCHAR(255) NOT NULL,'
            . 'stored_name VARCHAR(100) NOT NULL UNIQUE,'
            . 'mime_type VARCHAR(100) NOT NULL,'
            . 'size_bytes BIGINT UNSIGNED NOT NULL,'
            . 'width_px INT UNSIGNED NOT NULL,'
            . 'height_px INT UNSIGNED NOT NULL,'
            . 'sha256 CHAR(64) NOT NULL,'
            . 'alt_text VARCHAR(255) NOT NULL,'
            . 'sort_order INT NOT NULL DEFAULT 0,'
            . 'is_cover TINYINT(1) NOT NULL DEFAULT 0,'
            . 'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . 'CONSTRAINT fk_finalist_images_finalist FOREIGN KEY (finalist_id) REFERENCES finalists(id) ON DELETE CASCADE,'
            . 'INDEX idx_finalist_images_order (finalist_id, is_cover, sort_order, id)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        Settings::set('schema_finalist_images_v1', '1');
        self::$finalistImagesReady = true;
    }
}
