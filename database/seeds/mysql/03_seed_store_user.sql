-- Seed: 03 — liaisons utilisateur ↔ store
SET @admin_id  = (SELECT `id` FROM `users` WHERE `email` = 'admin@kintai.local');
SET @alice_id  = (SELECT `id` FROM `users` WHERE `email` = 'alice.martin@kintai.local');
SET @bob_id    = (SELECT `id` FROM `users` WHERE `email` = 'bob.dupont@kintai.local');
SET @chloe_id  = (SELECT `id` FROM `users` WHERE `email` = 'chloe.tanaka@kintai.local');
SET @yuki_id   = (SELECT `id` FROM `users` WHERE `email` = 'yuki.yamamoto@kintai.local');
SET @david_id  = (SELECT `id` FROM `users` WHERE `email` = 'david.leblanc@kintai.local');
SET @emma_id   = (SELECT `id` FROM `users` WHERE `email` = 'emma.sato@kintai.local');
SET @mshq_id   = (SELECT `id` FROM `stores` WHERE `code` = 'KTHQ');
SET @msparis_id= (SELECT `id` FROM `stores` WHERE `code` = 'KTPARIS');

INSERT IGNORE INTO `store_user`
    (`store_id`, `user_id`, `role`, `staff_code`, `is_manager`, `is_active`, `created_at`, `updated_at`)
VALUES
    -- Admin : rôle admin sur les deux stores
    (@mshq_id,    @admin_id,  'admin',   NULL,     1, 1, NOW(), NOW()),
    (@msparis_id, @admin_id,  'admin',   NULL,     1, 1, NOW(), NOW()),
    -- MSHQ
    (@mshq_id,    @alice_id,  'manager', NULL,     1, 1, NOW(), NOW()),
    (@mshq_id,    @bob_id,    'staff',   'STF001', 0, 1, NOW(), NOW()),
    (@mshq_id,    @chloe_id,  'staff',   'STF002', 0, 1, NOW(), NOW()),
    (@mshq_id,    @yuki_id,   'staff',   'STF003', 0, 1, NOW(), NOW()),
    -- MSPARIS
    (@msparis_id, @emma_id,   'manager', NULL,     1, 1, NOW(), NOW()),
    (@msparis_id, @david_id,  'staff',   'STF001', 0, 1, NOW(), NOW());
