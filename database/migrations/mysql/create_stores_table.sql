-- Migration: 002 — stores
-- No foreign key dependencies.
CREATE TABLE IF NOT EXISTS `stores` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`            VARCHAR(200)    NOT NULL,
    `code`            VARCHAR(20)     NOT NULL,
    `type`            VARCHAR(50)     NOT NULL DEFAULT 'retail',
    `timezone`        VARCHAR(50)     NOT NULL DEFAULT 'UTC',
    `currency`        CHAR(3)         NOT NULL DEFAULT 'EUR',
    `locale`          VARCHAR(10)     NOT NULL DEFAULT 'en',
    `address_street`  VARCHAR(255)    NULL DEFAULT NULL,
    `address_city`    VARCHAR(100)    NULL DEFAULT NULL,
    `address_postal`  VARCHAR(20)     NULL DEFAULT NULL,
    `address_country` VARCHAR(2)      NULL DEFAULT NULL,
    `phone`           VARCHAR(30)     NULL DEFAULT NULL,
    `email`           VARCHAR(255)    NULL DEFAULT NULL,
    `break_settings`  JSON            NOT NULL DEFAULT '{"min_shift_hours": 6, "break_minutes": 60}',
    `opening_hours`   JSON            NOT NULL DEFAULT '{}',
    `is_active`       TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`      DATETIME        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_stores_code`   (`code`),
    KEY `idx_stores_active` (`is_active`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


