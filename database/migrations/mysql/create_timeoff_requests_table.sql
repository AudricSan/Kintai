-- Migration: 007 — timeoff_requests
-- Depends on: stores, users
CREATE TABLE IF NOT EXISTS `timeoff_requests` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `store_id`     BIGINT UNSIGNED NOT NULL,
    `user_id`      BIGINT UNSIGNED NOT NULL,
    `type`         ENUM('vacation','sick','personal','unpaid','other') NOT NULL DEFAULT 'vacation',
    `start_date`   DATE            NOT NULL,
    `end_date`     DATE            NOT NULL,
    `reason`       TEXT            NULL DEFAULT NULL,
    `status`       ENUM('pending','approved','refused','cancelled') NOT NULL DEFAULT 'pending',
    `reviewed_by`  BIGINT UNSIGNED NULL DEFAULT NULL,
    `reviewed_at`  DATETIME        NULL DEFAULT NULL,
    `review_notes` TEXT            NULL DEFAULT NULL,
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_timeoff_user_dates`   (`user_id`,  `start_date`, `end_date`, `status`),
    KEY `idx_timeoff_store_status` (`store_id`, `status`,     `start_date`),
    KEY `idx_timeoff_store_dates`  (`store_id`, `start_date`, `end_date`),
    CONSTRAINT `fk_timeoff_store`    FOREIGN KEY (`store_id`)    REFERENCES `stores` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_timeoff_user`     FOREIGN KEY (`user_id`)     REFERENCES `users`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_timeoff_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
