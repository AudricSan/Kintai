-- Seed: 10 — affectations RBAC (role_assignments) des comptes de démonstration (SQLite)
-- Sans ceci, les comptes créés par 01_seed_users.sql/03_seed_store_user.sql
-- n'ont historiquement aucun droit réel (AuthService/PermissionService lisent
-- exclusivement role_assignments, jamais les anciennes colonnes de rôle).
PRAGMA foreign_keys = ON;

INSERT OR IGNORE INTO "role_assignments"
    ("user_id", "role_id", "scope_type", "scope_id", "created_at")
SELECT (SELECT id FROM users WHERE email='admin@kintai.local'),
       (SELECT id FROM roles WHERE slug='owner'), 'global', NULL, datetime('now')
UNION ALL
SELECT (SELECT id FROM users WHERE email='alice.martin@kintai.local'),
       (SELECT id FROM roles WHERE slug='manager'), 'store', (SELECT id FROM stores WHERE code='KTHQ'), datetime('now')
UNION ALL
SELECT (SELECT id FROM users WHERE email='bob.dupont@kintai.local'),
       (SELECT id FROM roles WHERE slug='employee'), 'store', (SELECT id FROM stores WHERE code='KTHQ'), datetime('now')
UNION ALL
SELECT (SELECT id FROM users WHERE email='chloe.tanaka@kintai.local'),
       (SELECT id FROM roles WHERE slug='employee'), 'store', (SELECT id FROM stores WHERE code='KTHQ'), datetime('now')
UNION ALL
SELECT (SELECT id FROM users WHERE email='yuki.yamamoto@kintai.local'),
       (SELECT id FROM roles WHERE slug='employee'), 'store', (SELECT id FROM stores WHERE code='KTHQ'), datetime('now')
UNION ALL
SELECT (SELECT id FROM users WHERE email='emma.sato@kintai.local'),
       (SELECT id FROM roles WHERE slug='manager'), 'store', (SELECT id FROM stores WHERE code='KTPARIS'), datetime('now')
UNION ALL
SELECT (SELECT id FROM users WHERE email='david.leblanc@kintai.local'),
       (SELECT id FROM roles WHERE slug='employee'), 'store', (SELECT id FROM stores WHERE code='KTPARIS'), datetime('now');
