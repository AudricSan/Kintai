-- Seed: 06 — demandes de congés (SQLite)
PRAGMA foreign_keys = ON;

INSERT INTO "timeoff_requests"
    ("store_id","user_id","type","start_date","end_date","reason",
     "status","reviewed_by","reviewed_at","review_notes","created_at","updated_at")
-- Bob : vacances de printemps — en attente
SELECT (SELECT id FROM stores WHERE code='KTHQ'),
       (SELECT id FROM users  WHERE email='bob.dupont@kintai.local'),
       'vacation','2026-03-10','2026-03-14','Vacances de printemps',
       'pending',NULL,NULL,NULL, datetime('now'),datetime('now')
UNION ALL
-- Chloé : maladie — approuvée par Alice
SELECT (SELECT id FROM stores WHERE code='KTHQ'),
       (SELECT id FROM users  WHERE email='chloe.tanaka@kintai.local'),
       'sick','2026-02-20','2026-02-20','Grippe',
       'approved',
       (SELECT id FROM users WHERE email='alice.martin@kintai.local'),
       '2026-02-19 18:30:00','Approuvé.',
       '2026-02-19 16:00:00','2026-02-19 18:30:00'
UNION ALL
-- Yuki : congé personnel — en attente
SELECT (SELECT id FROM stores WHERE code='KTHQ'),
       (SELECT id FROM users  WHERE email='yuki.yamamoto@kintai.local'),
       'personal','2026-03-05','2026-03-06','Obligation personnelle',
       'pending',NULL,NULL,NULL, datetime('now'),datetime('now')
UNION ALL
-- Alice : congé refusé — refusé par admin
SELECT (SELECT id FROM stores WHERE code='KTHQ'),
       (SELECT id FROM users  WHERE email='alice.martin@kintai.local'),
       'vacation','2026-03-16','2026-03-20','Congés demandés',
       'refused',
       (SELECT id FROM users WHERE email='admin@kintai.local'),
       '2026-03-01 09:00:00','Sous-effectif cette semaine.',
       '2026-02-28 14:00:00','2026-03-01 09:00:00'
UNION ALL
-- David : vacances — en attente
SELECT (SELECT id FROM stores WHERE code='KTPARIS'),
       (SELECT id FROM users  WHERE email='david.leblanc@kintai.local'),
       'vacation','2026-03-17','2026-03-21','Semaine de repos',
       'pending',NULL,NULL,NULL, datetime('now'),datetime('now')
UNION ALL
-- Emma : congés Pâques — approuvés par admin
SELECT (SELECT id FROM stores WHERE code='KTPARIS'),
       (SELECT id FROM users  WHERE email='emma.sato@kintai.local'),
       'vacation','2026-04-01','2026-04-07','Vacances de Pâques',
       'approved',
       (SELECT id FROM users WHERE email='admin@kintai.local'),
       '2026-03-25 10:00:00','Approuvé, bonne recharge !',
       '2026-03-20 09:00:00','2026-03-25 10:00:00';
