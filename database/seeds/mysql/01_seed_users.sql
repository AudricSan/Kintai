-- Seed: 01 — utilisateurs
-- Admin  : Admin1234!  → $2y$12$2yFMQT9Xu33nLyeVuJ./t.2.jLa52LEl5UMSGJUwPBaCv2e4uAFma
-- Staff  : Staff1234!  → $2y$12$JAjpz2DGOpHVJCGZoLReku7RfF2RwBy4uTgvXcD5sBsIaj0HTMzi6
INSERT IGNORE INTO `users`
    (`email`, `password_hash`, `first_name`, `last_name`, `display_name`,
     `color`, `is_admin`, `is_active`, `created_at`, `updated_at`)
VALUES
    ('admin@kintai.local',
     '$2y$12$2yFMQT9Xu33nLyeVuJ./t.2.jLa52LEl5UMSGJUwPBaCv2e4uAFma',
     'Super', 'Admin', 'Super Admin', '#6366F1', 1, 1, NOW(), NOW()),

    ('alice.martin@kintai.local',
     '$2y$12$JAjpz2DGOpHVJCGZoLReku7RfF2RwBy4uTgvXcD5sBsIaj0HTMzi6',
     'Alice', 'Martin', 'Alice Martin', '#10B981', 0, 1, NOW(), NOW()),

    ('bob.dupont@kintai.local',
     '$2y$12$JAjpz2DGOpHVJCGZoLReku7RfF2RwBy4uTgvXcD5sBsIaj0HTMzi6',
     'Bob', 'Dupont', 'Bob Dupont', '#F59E0B', 0, 1, NOW(), NOW()),

    ('chloe.tanaka@kintai.local',
     '$2y$12$JAjpz2DGOpHVJCGZoLReku7RfF2RwBy4uTgvXcD5sBsIaj0HTMzi6',
     'Chloé', 'Tanaka', 'Chloé Tanaka', '#EC4899', 0, 1, NOW(), NOW()),

    ('yuki.yamamoto@kintai.local',
     '$2y$12$JAjpz2DGOpHVJCGZoLReku7RfF2RwBy4uTgvXcD5sBsIaj0HTMzi6',
     'Yuki', 'Yamamoto', 'Yuki Yamamoto', '#8B5CF6', 0, 1, NOW(), NOW()),

    ('david.leblanc@kintai.local',
     '$2y$12$JAjpz2DGOpHVJCGZoLReku7RfF2RwBy4uTgvXcD5sBsIaj0HTMzi6',
     'David', 'Leblanc', 'David Leblanc', '#0EA5E9', 0, 1, NOW(), NOW()),

    ('emma.sato@kintai.local',
     '$2y$12$JAjpz2DGOpHVJCGZoLReku7RfF2RwBy4uTgvXcD5sBsIaj0HTMzi6',
     'Emma', 'Sato', 'Emma Sato', '#EF4444', 0, 1, NOW(), NOW());
