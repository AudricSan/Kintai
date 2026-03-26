-- Migration: 009 — audit_log
-- Depends on: stores (SET NULL), users (SET NULL)
-- Append-only table: no updated_at, no soft delete.
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `store_id`      BIGINT UNSIGNED NULL DEFAULT NULL,
    `user_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `action`        VARCHAR(50)     NOT NULL,
    `resource_type` VARCHAR(50)     NOT NULL,
    `resource_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
    `details`       JSON            NULL DEFAULT NULL,
    `ip_address`    VARCHAR(45)     NULL DEFAULT NULL,
    `user_agent`    VARCHAR(500)    NULL DEFAULT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_store_time` (`store_id`,     `created_at`),
    KEY `idx_audit_user_time`  (`user_id`,      `created_at`),
    KEY `idx_audit_resource`   (`resource_type`,`resource_id`),
    KEY `idx_audit_time`       (`created_at`),
    CONSTRAINT `fk_audit_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_audit_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
