-- Seed: 03 — liaisons utilisateur ↔ store (SQLite)
PRAGMA foreign_keys = ON;

INSERT OR IGNORE INTO "store_user"
    ("store_id", "user_id", "role", "staff_code", "is_manager", "is_active", "created_at", "updated_at")
SELECT (SELECT id FROM stores WHERE code='KTHQ'),    (SELECT id FROM users WHERE email='admin@kintai.local'),
       'admin', NULL, 1, 1, datetime('now'), datetime('now')
UNION ALL
SELECT (SELECT id FROM stores WHERE code='KTPARIS'), (SELECT id FROM users WHERE email='admin@kintai.local'),
       'admin', NULL, 1, 1, datetime('now'), datetime('now')
UNION ALL
SELECT (SELECT id FROM stores WHERE code='KTHQ'),    (SELECT id FROM users WHERE email='alice.martin@kintai.local'),
       'manager', NULL, 1, 1, datetime('now'), datetime('now')
UNION ALL
SELECT (SELECT id FROM stores WHERE code='KTHQ'),    (SELECT id FROM users WHERE email='bob.dupont@kintai.local'),
       'staff', 'STF001', 0, 1, datetime('now'), datetime('now')
UNION ALL
SELECT (SELECT id FROM stores WHERE code='KTHQ'),    (SELECT id FROM users WHERE email='chloe.tanaka@kintai.local'),
       'staff', 'STF002', 0, 1, datetime('now'), datetime('now')
UNION ALL
SELECT (SELECT id FROM stores WHERE code='KTHQ'),    (SELECT id FROM users WHERE email='yuki.yamamoto@kintai.local'),
       'staff', 'STF003', 0, 1, datetime('now'), datetime('now')
UNION ALL
SELECT (SELECT id FROM stores WHERE code='KTPARIS'), (SELECT id FROM users WHERE email='emma.sato@kintai.local'),
       'manager', NULL, 1, 1, datetime('now'), datetime('now')
UNION ALL
SELECT (SELECT id FROM stores WHERE code='KTPARIS'), (SELECT id FROM users WHERE email='david.leblanc@kintai.local'),
       'staff', 'STF001', 0, 1, datetime('now'), datetime('now');
