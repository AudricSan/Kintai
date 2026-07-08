-- Seed: 06 — demandes de congés
-- Statuts : pending / approved / refused
SET @admin_id  = (SELECT `id` FROM `users` WHERE `email` = 'admin@kintai.local');
SET @alice_id  = (SELECT `id` FROM `users` WHERE `email` = 'alice.martin@kintai.local');
SET @bob_id    = (SELECT `id` FROM `users` WHERE `email` = 'bob.dupont@kintai.local');
SET @chloe_id  = (SELECT `id` FROM `users` WHERE `email` = 'chloe.tanaka@kintai.local');
SET @yuki_id   = (SELECT `id` FROM `users` WHERE `email` = 'yuki.yamamoto@kintai.local');
SET @david_id  = (SELECT `id` FROM `users` WHERE `email` = 'david.leblanc@kintai.local');
SET @emma_id   = (SELECT `id` FROM `users` WHERE `email` = 'emma.sato@kintai.local');
SET @mshq_id   = (SELECT `id` FROM `stores` WHERE `code` = 'KTHQ');
SET @msparis_id= (SELECT `id` FROM `stores` WHERE `code` = 'KTPARIS');

INSERT INTO `timeoff_requests`
    (`store_id`, `user_id`, `type`, `start_date`, `end_date`, `reason`,
     `status`, `reviewed_by`, `reviewed_at`, `review_notes`, `created_at`, `updated_at`)
VALUES
    -- Bob : vacances de printemps — en attente
    (@mshq_id, @bob_id,   'vacation', '2026-03-10', '2026-03-14',
     'Vacances de printemps',
     'pending', NULL, NULL, NULL, NOW(), NOW()),

    -- Chloé : maladie — approuvée par Alice
    (@mshq_id, @chloe_id, 'sick',     '2026-02-20', '2026-02-20',
     'Grippe',
     'approved', @alice_id, '2026-02-19 18:30:00', 'Approuvé.',
     '2026-02-19 16:00:00', '2026-02-19 18:30:00'),

    -- Yuki : congé personnel — en attente
    (@mshq_id, @yuki_id,  'personal', '2026-03-05', '2026-03-06',
     'Obligation personnelle',
     'pending', NULL, NULL, NULL, NOW(), NOW()),

    -- Alice : congé refusé (sous-effectif) — refusé par admin
    (@mshq_id, @alice_id, 'vacation', '2026-03-16', '2026-03-20',
     'Congés demandés',
     'refused', @admin_id, '2026-03-01 09:00:00', 'Sous-effectif cette semaine.',
     '2026-02-28 14:00:00', '2026-03-01 09:00:00'),

    -- David : vacances — en attente
    (@msparis_id, @david_id, 'vacation', '2026-03-17', '2026-03-21',
     'Semaine de repos',
     'pending', NULL, NULL, NULL, NOW(), NOW()),

    -- Emma : congés Pâques — approuvés par admin
    (@msparis_id, @emma_id,  'vacation', '2026-04-01', '2026-04-07',
     'Vacances de Pâques',
     'approved', @admin_id, '2026-03-25 10:00:00', 'Approuvé, bonne recharge !',
     '2026-03-20 09:00:00', '2026-03-25 10:00:00');
