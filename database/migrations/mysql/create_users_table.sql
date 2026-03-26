-- Migration: 001 — users
-- No foreign key dependencies.
CREATE TABLE IF NOT EXISTS `users` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`             VARCHAR(255)    NOT NULL,
    `password_hash`     VARCHAR(255)    NOT NULL,
    `first_name`        VARCHAR(100)    NOT NULL,
    `last_name`         VARCHAR(100)    NOT NULL,
    `display_name`      VARCHAR(200)    NOT NULL,
    `phone`             VARCHAR(30)     NULL DEFAULT NULL,
    `profile_image`     VARCHAR(500)    NULL DEFAULT NULL,
    `color`             CHAR(7)         NOT NULL DEFAULT '#3B82F6',
    `is_admin`          TINYINT(1)      NOT NULL DEFAULT 0,
    `is_active`         TINYINT(1)      NOT NULL DEFAULT 1,
    `email_verified_at` DATETIME        NULL DEFAULT NULL,
    `last_login_at`     DATETIME        NULL DEFAULT NULL,
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`        DATETIME        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_email` (`email`),
    KEY `idx_users_active`  (`is_active`, `deleted_at`),
    KEY `idx_users_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
