-- Migration: 006 — availabilities
-- Depends on: stores, users
CREATE TABLE IF NOT EXISTS `availabilities` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `store_id`        BIGINT UNSIGNED NOT NULL,
    `user_id`         BIGINT UNSIGNED NOT NULL,
    `day_of_week`     TINYINT UNSIGNED NOT NULL,
    `start_time`      TIME            NOT NULL,
    `end_time`        TIME            NOT NULL,
    `is_available`    TINYINT(1)      NOT NULL DEFAULT 1,
    `effective_from`  DATE            NULL DEFAULT NULL,
    `effective_until` DATE            NULL DEFAULT NULL,
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_avail_user_day`   (`user_id`,  `day_of_week`, `is_available`),
    KEY `idx_avail_store_user` (`store_id`, `user_id`),
    CONSTRAINT `fk_avail_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_avail_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
