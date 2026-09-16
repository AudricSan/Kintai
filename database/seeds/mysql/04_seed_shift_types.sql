-- Seed: 04 — types de shifts (3 par store)
-- Un type de shift est désormais rattaché à ses stores via la table pivot
-- shift_type_stores (many-to-many) plutôt qu'une colonne store_id : chaque
-- INSERT ligne par ligne (pas de VALUES multi-lignes) est suivi de son
-- affectation via LAST_INSERT_ID(), qui ne renvoie l'id que de la dernière
-- ligne insérée par la requête précédente.
SET @mshq_id    = (SELECT `id` FROM `stores` WHERE `code` = 'KTHQ');
SET @msparis_id = (SELECT `id` FROM `stores` WHERE `code` = 'KTPARIS');

-- MSHQ
INSERT INTO `shift_types` (`name`, `code`, `start_time`, `end_time`, `hourly_rate`, `color`, `sort_order`, `is_active`, `created_at`, `updated_at`)
VALUES ('Matin', 'MORNING', '06:00:00', '14:00:00', 0, '#FBBF24', 1, 1, NOW(), NOW());
INSERT IGNORE INTO `shift_type_stores` (`shift_type_id`, `store_id`) VALUES (LAST_INSERT_ID(), @mshq_id);

INSERT INTO `shift_types` (`name`, `code`, `start_time`, `end_time`, `hourly_rate`, `color`, `sort_order`, `is_active`, `created_at`, `updated_at`)
VALUES ('Après-midi', 'AFTERNOON', '14:00:00', '22:00:00', 0, '#34D399', 2, 1, NOW(), NOW());
INSERT IGNORE INTO `shift_type_stores` (`shift_type_id`, `store_id`) VALUES (LAST_INSERT_ID(), @mshq_id);

INSERT INTO `shift_types` (`name`, `code`, `start_time`, `end_time`, `hourly_rate`, `color`, `sort_order`, `is_active`, `created_at`, `updated_at`)
VALUES ('Nuit', 'NIGHT', '22:00:00', '06:00:00', 0, '#818CF8', 3, 1, NOW(), NOW());
INSERT IGNORE INTO `shift_type_stores` (`shift_type_id`, `store_id`) VALUES (LAST_INSERT_ID(), @mshq_id);

-- MSPARIS
INSERT INTO `shift_types` (`name`, `code`, `start_time`, `end_time`, `hourly_rate`, `color`, `sort_order`, `is_active`, `created_at`, `updated_at`)
VALUES ('Matin', 'MORNING', '07:00:00', '15:00:00', 0, '#FBBF24', 1, 1, NOW(), NOW());
INSERT IGNORE INTO `shift_type_stores` (`shift_type_id`, `store_id`) VALUES (LAST_INSERT_ID(), @msparis_id);

INSERT INTO `shift_types` (`name`, `code`, `start_time`, `end_time`, `hourly_rate`, `color`, `sort_order`, `is_active`, `created_at`, `updated_at`)
VALUES ('Après-midi', 'AFTERNOON', '15:00:00', '23:00:00', 0, '#34D399', 2, 1, NOW(), NOW());
INSERT IGNORE INTO `shift_type_stores` (`shift_type_id`, `store_id`) VALUES (LAST_INSERT_ID(), @msparis_id);

INSERT INTO `shift_types` (`name`, `code`, `start_time`, `end_time`, `hourly_rate`, `color`, `sort_order`, `is_active`, `created_at`, `updated_at`)
VALUES ('Nuit', 'NIGHT', '23:00:00', '07:00:00', 0, '#818CF8', 3, 1, NOW(), NOW());
INSERT IGNORE INTO `shift_type_stores` (`shift_type_id`, `store_id`) VALUES (LAST_INSERT_ID(), @msparis_id);
