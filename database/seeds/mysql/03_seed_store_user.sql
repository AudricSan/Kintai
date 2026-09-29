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
    (`store_id`, `user_id`, `staff_code`, `is_active`, `created_at`, `updated_at`)
VALUES
    -- Admin : membre des deux stores (rôle Owner géré par role_assignments, portée globale)
    (@mshq_id,    @admin_id,  NULL,     1, NOW(), NOW()),
    (@msparis_id, @admin_id,  NULL,     1, NOW(), NOW()),
    -- MSHQ
    (@mshq_id,    @alice_id,  NULL,     1, NOW(), NOW()),
    (@mshq_id,    @bob_id,    'STF001', 1, NOW(), NOW()),
    (@mshq_id,    @chloe_id,  'STF002', 1, NOW(), NOW()),
    (@mshq_id,    @yuki_id,   'STF003', 1, NOW(), NOW()),
    -- MSPARIS
    (@msparis_id, @emma_id,   NULL,     1, NOW(), NOW()),
    (@msparis_id, @david_id,  'STF001', 1, NOW(), NOW());
