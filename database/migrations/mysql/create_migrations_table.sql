-- Migration: 000 — tracking table (must always run first)
CREATE TABLE IF NOT EXISTS `migrations` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `migration`   VARCHAR(255) NOT NULL,
    `executed_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_migration_name` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
