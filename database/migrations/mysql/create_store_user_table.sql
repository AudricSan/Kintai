-- Migration: 003 — store_user (pivot)
-- Depends on: users, stores
CREATE TABLE IF NOT EXISTS `store_user` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `store_id`      BIGINT UNSIGNED NOT NULL,
    `user_id`       BIGINT UNSIGNED NOT NULL,
    `role`          ENUM('admin','manager','staff') NOT NULL DEFAULT 'staff',
    `staff_code`    VARCHAR(20)     NULL DEFAULT NULL,
    `hire_date`     DATE            NULL DEFAULT NULL,
    `contract_type` VARCHAR(50)     NULL DEFAULT NULL,
    `is_manager`    TINYINT(1)      NOT NULL DEFAULT 0,
    `hourly_rates`  JSON            NULL DEFAULT NULL,
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_store_user`       (`store_id`, `user_id`),
    UNIQUE KEY `uk_store_staff_code` (`store_id`, `staff_code`),
    KEY `idx_store_user_user` (`user_id`),
    KEY `idx_store_user_role` (`store_id`, `role`),
    CONSTRAINT `fk_store_user_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_store_user_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
