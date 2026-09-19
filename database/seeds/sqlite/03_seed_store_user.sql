-- Seed: 03 — liaisons utilisateur ↔ store (SQLite)
PRAGMA foreign_keys = ON;

INSERT OR IGNORE INTO "store_user"
    ("store_id", "user_id", "staff_code", "is_active", "created_at", "updated_at")
SELECT (SELECT id FROM stores WHERE code='KTHQ'),    (SELECT id FROM users WHERE email='admin@kintai.local'),
       NULL, 1, datetime('now'), datetime('now')
UNION ALL
SELECT (SELECT id FROM stores WHERE code='KTPARIS'), (SELECT id FROM users WHERE email='admin@kintai.local'),
       NULL, 1, datetime('now'), datetime('now')
UNION ALL
SELECT (SELECT id FROM stores WHERE code='KTHQ'),    (SELECT id FROM users WHERE email='alice.martin@kintai.local'),
       NULL, 1, datetime('now'), datetime('now')
UNION ALL
SELECT (SELECT id FROM stores WHERE code='KTHQ'),    (SELECT id FROM users WHERE email='bob.dupont@kintai.local'),
       'STF001', 1, datetime('now'), datetime('now')
UNION ALL
SELECT (SELECT id FROM stores WHERE code='KTHQ'),    (SELECT id FROM users WHERE email='chloe.tanaka@kintai.local'),
       'STF002', 1, datetime('now'), datetime('now')
UNION ALL
SELECT (SELECT id FROM stores WHERE code='KTHQ'),    (SELECT id FROM users WHERE email='yuki.yamamoto@kintai.local'),
       'STF003', 1, datetime('now'), datetime('now')
UNION ALL
SELECT (SELECT id FROM stores WHERE code='KTPARIS'), (SELECT id FROM users WHERE email='emma.sato@kintai.local'),
       NULL, 1, datetime('now'), datetime('now')
UNION ALL
SELECT (SELECT id FROM stores WHERE code='KTPARIS'), (SELECT id FROM users WHERE email='david.leblanc@kintai.local'),
       'STF001', 1, datetime('now'), datetime('now');
