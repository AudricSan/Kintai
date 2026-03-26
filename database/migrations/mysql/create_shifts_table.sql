-- Migration: 005 — shifts
-- Depends on: stores, users, shift_types
-- Design: start_time/end_time are wall-clock TIME values.
-- starts_at/ends_at are absolute UTC DATETIME for conflict detection.
CREATE TABLE IF NOT EXISTS `shifts` (
    `id`               BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `store_id`         BIGINT UNSIGNED   NOT NULL,
    `user_id`          BIGINT UNSIGNED   NOT NULL,
    `shift_date`       DATE              NOT NULL,
    `start_time`       TIME              NOT NULL,
    `end_time`         TIME              NOT NULL,
    `shift_type_id`    BIGINT UNSIGNED   NULL DEFAULT NULL,
    `cross_midnight`   TINYINT(1)        NOT NULL DEFAULT 0,
    `pause_minutes`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `duration_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `starts_at`        DATETIME          NOT NULL,
    `ends_at`          DATETIME          NOT NULL,
    `notes`            TEXT              NULL DEFAULT NULL,
    `created_by`       BIGINT UNSIGNED   NULL DEFAULT NULL,
    `created_at`       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`       DATETIME          NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_shifts_store_date` (`store_id`, `shift_date`, `deleted_at`),
    KEY `idx_shifts_user_date`  (`user_id`,  `shift_date`, `deleted_at`),
    KEY `idx_shifts_user_time`  (`user_id`,  `starts_at`,  `ends_at`, `deleted_at`),
    KEY `idx_shifts_store_user` (`store_id`, `user_id`,    `shift_date`),
    CONSTRAINT `fk_shifts_store`   FOREIGN KEY (`store_id`)      REFERENCES `stores`      (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_shifts_user`    FOREIGN KEY (`user_id`)       REFERENCES `users`       (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_shifts_type`    FOREIGN KEY (`shift_type_id`) REFERENCES `shift_types` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_shifts_creator` FOREIGN KEY (`created_by`)    REFERENCES `users`       (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
