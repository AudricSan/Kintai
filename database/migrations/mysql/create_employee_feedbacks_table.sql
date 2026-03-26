CREATE TABLE IF NOT EXISTS `employee_feedbacks` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `store_id`   INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NULL DEFAULT NULL,
    `shift_id`   INT UNSIGNED NULL DEFAULT NULL,
    `category`   ENUM('shift','schedule','app','other') NOT NULL DEFAULT 'other',
    `rating`     TINYINT UNSIGNED NULL DEFAULT NULL,
    `message`    TEXT NOT NULL,
    `anonymous`  TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_fb_rating` CHECK (`rating` IS NULL OR (`rating` >= 1 AND `rating` <= 5)),
    FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)  REFERENCES `users`  (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NULL est ignoré dans les index UNIQUE en MySQL
CREATE UNIQUE INDEX `uq_employee_feedback_shift` ON `employee_feedbacks` (`shift_id`);
CREATE INDEX `idx_employee_feedback_store` ON `employee_feedbacks` (`store_id`, `created_at`);
