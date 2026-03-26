-- Migration: 004 — shift_types
-- Depends on: stores
CREATE TABLE IF NOT EXISTS `shift_types` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `store_id`    BIGINT UNSIGNED NOT NULL,
    `name`        VARCHAR(100)    NOT NULL,
    `code`        VARCHAR(30)     NOT NULL,
    `start_time`  TIME            NOT NULL,
    `end_time`    TIME            NOT NULL,
    `hourly_rate` DECIMAL(10,2)   NOT NULL DEFAULT 0,
    `color`       CHAR(7)         NOT NULL DEFAULT '#3B82F6',
    `sort_order`  TINYINT         NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_shift_type_store_code` (`store_id`, `code`),
    KEY `idx_shift_type_active` (`store_id`, `is_active`, `sort_order`),
    CONSTRAINT `fk_shift_type_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
