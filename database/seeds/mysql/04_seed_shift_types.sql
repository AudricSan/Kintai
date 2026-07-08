-- Seed: 04 — types de shifts (3 par store)
SET @mshq_id    = (SELECT `id` FROM `stores` WHERE `code` = 'KTHQ');
SET @msparis_id = (SELECT `id` FROM `stores` WHERE `code` = 'KTPARIS');

INSERT IGNORE INTO `shift_types`
    (`store_id`, `name`, `code`, `start_time`, `end_time`,
     `hourly_rate`, `color`, `sort_order`, `is_active`, `created_at`, `updated_at`)
VALUES
    -- MSHQ
    (@mshq_id,    'Matin',       'MORNING',   '06:00:00', '14:00:00', 0, '#FBBF24', 1, 1, NOW(), NOW()),
    (@mshq_id,    'Après-midi',  'AFTERNOON', '14:00:00', '22:00:00', 0, '#34D399', 2, 1, NOW(), NOW()),
    (@mshq_id,    'Nuit',        'NIGHT',     '22:00:00', '06:00:00', 0, '#818CF8', 3, 1, NOW(), NOW()),
    -- MSPARIS
    (@msparis_id, 'Matin',       'MORNING',   '07:00:00', '15:00:00', 0, '#FBBF24', 1, 1, NOW(), NOW()),
    (@msparis_id, 'Après-midi',  'AFTERNOON', '15:00:00', '23:00:00', 0, '#34D399', 2, 1, NOW(), NOW()),
    (@msparis_id, 'Nuit',        'NIGHT',     '23:00:00', '07:00:00', 0, '#818CF8', 3, 1, NOW(), NOW());
