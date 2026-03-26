-- Seed: 05 — plannings de shifts (semaines 2026-02-23 et 2026-03-02)
-- duration_minutes = (durée brute en minutes) - pause_minutes
-- Matin/Après-midi : 8h - 60 min pause = 420 min net
-- Nuit             : 8h - 60 min pause = 420 min net, cross_midnight = 1
SET @admin_id   = (SELECT `id` FROM `users` WHERE `email` = 'admin@kintai.local');
SET @alice_id   = (SELECT `id` FROM `users` WHERE `email` = 'alice.martin@kintai.local');
SET @bob_id     = (SELECT `id` FROM `users` WHERE `email` = 'bob.dupont@kintai.local');
SET @chloe_id   = (SELECT `id` FROM `users` WHERE `email` = 'chloe.tanaka@kintai.local');
SET @yuki_id    = (SELECT `id` FROM `users` WHERE `email` = 'yuki.yamamoto@kintai.local');
SET @david_id   = (SELECT `id` FROM `users` WHERE `email` = 'david.leblanc@kintai.local');
SET @emma_id    = (SELECT `id` FROM `users` WHERE `email` = 'emma.sato@kintai.local');

SET @mshq_id    = (SELECT `id` FROM `stores` WHERE `code` = 'KTHQ');
SET @msparis_id = (SELECT `id` FROM `stores` WHERE `code` = 'KTPARIS');

SET @mshq_am    = (SELECT `id` FROM `shift_types` WHERE `code`='MORNING'   AND `store_id`=@mshq_id);
SET @mshq_pm    = (SELECT `id` FROM `shift_types` WHERE `code`='AFTERNOON' AND `store_id`=@mshq_id);
SET @mshq_nuit  = (SELECT `id` FROM `shift_types` WHERE `code`='NIGHT'     AND `store_id`=@mshq_id);
SET @msp_am     = (SELECT `id` FROM `shift_types` WHERE `code`='MORNING'   AND `store_id`=@msparis_id);
SET @msp_pm     = (SELECT `id` FROM `shift_types` WHERE `code`='AFTERNOON' AND `store_id`=@msparis_id);

INSERT INTO `shifts`
    (`store_id`, `user_id`, `shift_date`, `start_time`, `end_time`, `shift_type_id`,
     `cross_midnight`, `pause_minutes`, `duration_minutes`,
     `starts_at`, `ends_at`, `created_by`, `created_at`, `updated_at`)
VALUES
    -- ── MSHQ · semaine du 23 février ────────────────────────────────────────
    -- Alice : Matin lundi → mercredi
    (@mshq_id, @alice_id, '2026-02-23', '06:00:00','14:00:00', @mshq_am,   0,60,420, '2026-02-23 06:00:00','2026-02-23 14:00:00', @admin_id, NOW(),NOW()),
    (@mshq_id, @alice_id, '2026-02-24', '06:00:00','14:00:00', @mshq_am,   0,60,420, '2026-02-24 06:00:00','2026-02-24 14:00:00', @admin_id, NOW(),NOW()),
    (@mshq_id, @alice_id, '2026-02-25', '06:00:00','14:00:00', @mshq_am,   0,60,420, '2026-02-25 06:00:00','2026-02-25 14:00:00', @admin_id, NOW(),NOW()),
    -- Bob : Après-midi lundi → mercredi
    (@mshq_id, @bob_id,   '2026-02-23', '14:00:00','22:00:00', @mshq_pm,   0,60,420, '2026-02-23 14:00:00','2026-02-23 22:00:00', @admin_id, NOW(),NOW()),
    (@mshq_id, @bob_id,   '2026-02-24', '14:00:00','22:00:00', @mshq_pm,   0,60,420, '2026-02-24 14:00:00','2026-02-24 22:00:00', @admin_id, NOW(),NOW()),
    (@mshq_id, @bob_id,   '2026-02-25', '14:00:00','22:00:00', @mshq_pm,   0,60,420, '2026-02-25 14:00:00','2026-02-25 22:00:00', @admin_id, NOW(),NOW()),
    -- Chloé : Nuit lundi → mardi (cross midnight)
    (@mshq_id, @chloe_id, '2026-02-23', '22:00:00','06:00:00', @mshq_nuit, 1,60,420, '2026-02-23 22:00:00','2026-02-24 06:00:00', @admin_id, NOW(),NOW()),
    (@mshq_id, @chloe_id, '2026-02-24', '22:00:00','06:00:00', @mshq_nuit, 1,60,420, '2026-02-24 22:00:00','2026-02-25 06:00:00', @admin_id, NOW(),NOW()),
    -- Yuki : Après-midi jeudi → vendredi
    (@mshq_id, @yuki_id,  '2026-02-26', '14:00:00','22:00:00', @mshq_pm,   0,60,420, '2026-02-26 14:00:00','2026-02-26 22:00:00', @admin_id, NOW(),NOW()),
    (@mshq_id, @yuki_id,  '2026-02-27', '14:00:00','22:00:00', @mshq_pm,   0,60,420, '2026-02-27 14:00:00','2026-02-27 22:00:00', @admin_id, NOW(),NOW()),

    -- ── MSPARIS · semaine du 23 février ─────────────────────────────────────
    -- David : Matin lundi, mercredi
    (@msparis_id, @david_id, '2026-02-23', '07:00:00','15:00:00', @msp_am, 0,60,420, '2026-02-23 07:00:00','2026-02-23 15:00:00', @admin_id, NOW(),NOW()),
    (@msparis_id, @david_id, '2026-02-25', '07:00:00','15:00:00', @msp_am, 0,60,420, '2026-02-25 07:00:00','2026-02-25 15:00:00', @admin_id, NOW(),NOW()),
    -- Emma : Après-midi mardi, jeudi
    (@msparis_id, @emma_id,  '2026-02-24', '15:00:00','23:00:00', @msp_pm, 0,60,420, '2026-02-24 15:00:00','2026-02-24 23:00:00', @admin_id, NOW(),NOW()),
    (@msparis_id, @emma_id,  '2026-02-26', '15:00:00','23:00:00', @msp_pm, 0,60,420, '2026-02-26 15:00:00','2026-02-26 23:00:00', @admin_id, NOW(),NOW()),

    -- ── MSHQ · semaine du 2 mars ─────────────────────────────────────────────
    -- Alice : Matin lundi → mardi
    (@mshq_id, @alice_id, '2026-03-02', '06:00:00','14:00:00', @mshq_am,   0,60,420, '2026-03-02 06:00:00','2026-03-02 14:00:00', @admin_id, NOW(),NOW()),
    (@mshq_id, @alice_id, '2026-03-03', '06:00:00','14:00:00', @mshq_am,   0,60,420, '2026-03-03 06:00:00','2026-03-03 14:00:00', @admin_id, NOW(),NOW()),
    -- Bob : Après-midi lundi → mardi
    (@mshq_id, @bob_id,   '2026-03-02', '14:00:00','22:00:00', @mshq_pm,   0,60,420, '2026-03-02 14:00:00','2026-03-02 22:00:00', @admin_id, NOW(),NOW()),
    (@mshq_id, @bob_id,   '2026-03-03', '14:00:00','22:00:00', @mshq_pm,   0,60,420, '2026-03-03 14:00:00','2026-03-03 22:00:00', @admin_id, NOW(),NOW()),
    -- Chloé : Nuit lundi (cross midnight)
    (@mshq_id, @chloe_id, '2026-03-02', '22:00:00','06:00:00', @mshq_nuit, 1,60,420, '2026-03-02 22:00:00','2026-03-03 06:00:00', @admin_id, NOW(),NOW()),

    -- ── MSPARIS · semaine du 2 mars ──────────────────────────────────────────
    -- David : Matin lundi, mardi, jeudi
    (@msparis_id, @david_id, '2026-03-02', '07:00:00','15:00:00', @msp_am, 0,60,420, '2026-03-02 07:00:00','2026-03-02 15:00:00', @admin_id, NOW(),NOW()),
    (@msparis_id, @david_id, '2026-03-03', '07:00:00','15:00:00', @msp_am, 0,60,420, '2026-03-03 07:00:00','2026-03-03 15:00:00', @admin_id, NOW(),NOW()),
    (@msparis_id, @david_id, '2026-03-05', '07:00:00','15:00:00', @msp_am, 0,60,420, '2026-03-05 07:00:00','2026-03-05 15:00:00', @admin_id, NOW(),NOW()),
    -- Emma : Après-midi mercredi, vendredi
    (@msparis_id, @emma_id,  '2026-03-04', '15:00:00','23:00:00', @msp_pm, 0,60,420, '2026-03-04 15:00:00','2026-03-04 23:00:00', @admin_id, NOW(),NOW()),
    (@msparis_id, @emma_id,  '2026-03-06', '15:00:00','23:00:00', @msp_pm, 0,60,420, '2026-03-06 15:00:00','2026-03-06 23:00:00', @admin_id, NOW(),NOW());
