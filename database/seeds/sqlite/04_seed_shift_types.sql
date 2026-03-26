-- Seed: 04 — types de shifts (SQLite)
PRAGMA foreign_keys = ON;

INSERT OR IGNORE INTO "shift_types"
    ("store_id", "name", "code", "start_time", "end_time",
     "hourly_rate", "color", "sort_order", "is_active", "created_at", "updated_at")
SELECT (SELECT id FROM stores WHERE code='KTHQ'),    'Matin',      'MORNING',   '06:00', '14:00', 0.0, '#FBBF24', 1, 1, datetime('now'), datetime('now')
UNION ALL
SELECT (SELECT id FROM stores WHERE code='KTHQ'),    'Après-midi', 'AFTERNOON', '14:00', '22:00', 0.0, '#34D399', 2, 1, datetime('now'), datetime('now')
UNION ALL
SELECT (SELECT id FROM stores WHERE code='KTHQ'),    'Nuit',       'NIGHT',     '22:00', '06:00', 0.0, '#818CF8', 3, 1, datetime('now'), datetime('now')
UNION ALL
SELECT (SELECT id FROM stores WHERE code='KTPARIS'), 'Matin',      'MORNING',   '07:00', '15:00', 0.0, '#FBBF24', 1, 1, datetime('now'), datetime('now')
UNION ALL
SELECT (SELECT id FROM stores WHERE code='KTPARIS'), 'Après-midi', 'AFTERNOON', '15:00', '23:00', 0.0, '#34D399', 2, 1, datetime('now'), datetime('now')
UNION ALL
SELECT (SELECT id FROM stores WHERE code='KTPARIS'), 'Nuit',       'NIGHT',     '23:00', '07:00', 0.0, '#818CF8', 3, 1, datetime('now'), datetime('now');
