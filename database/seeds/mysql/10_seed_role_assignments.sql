-- Seed: 10 — affectations RBAC (role_assignments) des comptes de démonstration
-- Sans ceci, les comptes créés par 01_seed_users.sql/03_seed_store_user.sql
-- n'ont historiquement aucun droit réel (AuthService/PermissionService lisent
-- exclusivement role_assignments, jamais les anciennes colonnes de rôle).
SET @admin_id  = (SELECT `id` FROM `users` WHERE `email` = 'admin@kintai.local');
SET @alice_id  = (SELECT `id` FROM `users` WHERE `email` = 'alice.martin@kintai.local');
SET @bob_id    = (SELECT `id` FROM `users` WHERE `email` = 'bob.dupont@kintai.local');
SET @chloe_id  = (SELECT `id` FROM `users` WHERE `email` = 'chloe.tanaka@kintai.local');
SET @yuki_id   = (SELECT `id` FROM `users` WHERE `email` = 'yuki.yamamoto@kintai.local');
SET @david_id  = (SELECT `id` FROM `users` WHERE `email` = 'david.leblanc@kintai.local');
SET @emma_id   = (SELECT `id` FROM `users` WHERE `email` = 'emma.sato@kintai.local');
SET @mshq_id   = (SELECT `id` FROM `stores` WHERE `code` = 'KTHQ');
SET @msparis_id= (SELECT `id` FROM `stores` WHERE `code` = 'KTPARIS');
SET @owner_role_id    = (SELECT `id` FROM `roles` WHERE `slug` = 'owner');
SET @manager_role_id  = (SELECT `id` FROM `roles` WHERE `slug` = 'manager');
SET @employee_role_id = (SELECT `id` FROM `roles` WHERE `slug` = 'employee');

INSERT IGNORE INTO `role_assignments`
    (`user_id`, `role_id`, `scope_type`, `scope_id`, `created_at`)
VALUES
    -- Admin : Owner en portée globale, indépendant de tout store
    (@admin_id, @owner_role_id,    'global', NULL,      NOW()),
    -- MSHQ
    (@alice_id, @manager_role_id,  'store',  @mshq_id,  NOW()),
    (@bob_id,   @employee_role_id, 'store',  @mshq_id,  NOW()),
    (@chloe_id, @employee_role_id, 'store',  @mshq_id,  NOW()),
    (@yuki_id,  @employee_role_id, 'store',  @mshq_id,  NOW()),
    -- MSPARIS
    (@emma_id,  @manager_role_id,  'store',  @msparis_id, NOW()),
    (@david_id, @employee_role_id, 'store',  @msparis_id, NOW());
