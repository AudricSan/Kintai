-- Migration: user_shift_type_rates — taux horaire personnalisé par employé et type de shift
CREATE TABLE IF NOT EXISTS `user_shift_type_rates` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       BIGINT UNSIGNED NOT NULL,
    `shift_type_id` BIGINT UNSIGNED NOT NULL,
    `hourly_rate`   DECIMAL(10,2)   NOT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_shift_type_rates` (`user_id`, `shift_type_id`),
    KEY `idx_user_shift_type_rates_user` (`user_id`),
    CONSTRAINT `fk_ustr_user`       FOREIGN KEY (`user_id`)       REFERENCES `users`(`id`)       ON DELETE CASCADE,
    CONSTRAINT `fk_ustr_shift_type` FOREIGN KEY (`shift_type_id`) REFERENCES `shift_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
