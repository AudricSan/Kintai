-- Migration: 011 — sessions
-- No foreign key constraints (user_id is a soft reference only).
CREATE TABLE IF NOT EXISTS `sessions` (
    `id`            VARCHAR(128) NOT NULL,
    `user_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `ip_address`    VARCHAR(45)  NULL DEFAULT NULL,
    `user_agent`    VARCHAR(500) NULL DEFAULT NULL,
    `payload`       MEDIUMTEXT   NOT NULL,
    `last_activity` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_sessions_user`     (`user_id`),
    KEY `idx_sessions_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
