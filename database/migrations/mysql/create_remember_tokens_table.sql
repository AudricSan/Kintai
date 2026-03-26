-- Migration: 010 — remember_tokens
-- Depends on: users
CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      BIGINT UNSIGNED NOT NULL,
    `token_hash`   VARCHAR(64)     NOT NULL,
    `expires_at`   DATETIME        NOT NULL,
    `last_used_at` DATETIME        NULL DEFAULT NULL,
    `ip_address`   VARCHAR(45)     NULL DEFAULT NULL,
    `user_agent`   VARCHAR(500)    NULL DEFAULT NULL,
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_remember_token`   (`token_hash`),
    KEY `idx_remember_user`    (`user_id`),
    KEY `idx_remember_expires` (`expires_at`),
    CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
