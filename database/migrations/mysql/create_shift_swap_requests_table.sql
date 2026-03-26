-- Migration: 008 — shift_swap_requests
-- Depends on: stores, users, shifts
CREATE TABLE IF NOT EXISTS `shift_swap_requests` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `store_id`            BIGINT UNSIGNED NOT NULL,
    `requester_id`        BIGINT UNSIGNED NOT NULL,
    `target_id`           BIGINT UNSIGNED NOT NULL,
    `requester_shift_id`  BIGINT UNSIGNED NOT NULL,
    `target_shift_id`     BIGINT UNSIGNED NULL DEFAULT NULL,
    `status`              ENUM('pending','accepted','refused','cancelled') NOT NULL DEFAULT 'pending',
    `reason`              TEXT            NULL DEFAULT NULL,
    `reviewed_by`         BIGINT UNSIGNED NULL DEFAULT NULL,
    `reviewed_at`         DATETIME        NULL DEFAULT NULL,
    `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_swap_store_status` (`store_id`,     `status`),
    KEY `idx_swap_requester`    (`requester_id`, `status`),
    KEY `idx_swap_target`       (`target_id`,    `status`),
    CONSTRAINT `fk_swap_store`     FOREIGN KEY (`store_id`)           REFERENCES `stores` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_swap_requester` FOREIGN KEY (`requester_id`)       REFERENCES `users`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_swap_target`    FOREIGN KEY (`target_id`)          REFERENCES `users`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_swap_req_shift` FOREIGN KEY (`requester_shift_id`) REFERENCES `shifts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_swap_tgt_shift` FOREIGN KEY (`target_shift_id`)    REFERENCES `shifts` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_swap_reviewer`  FOREIGN KEY (`reviewed_by`)        REFERENCES `users`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
