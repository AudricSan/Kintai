-- Seed: 02 — stores (SQLite)
PRAGMA foreign_keys = ON;

INSERT OR IGNORE INTO "stores"
    ("name", "code", "type", "timezone", "currency", "locale",
     "break_settings", "opening_hours", "is_active", "created_at", "updated_at")
VALUES
    (
        'Kintai HQ', 'KTHQ', 'retail', 'Asia/Tokyo', 'JPY', 'ja',
        '{"min_shift_hours": 6, "break_minutes": 60}',
        '{"mon":{"open":"09:00","close":"20:00"},"tue":{"open":"09:00","close":"20:00"},"wed":{"open":"09:00","close":"20:00"},"thu":{"open":"09:00","close":"20:00"},"fri":{"open":"09:00","close":"20:00"},"sat":{"open":"10:00","close":"19:00"},"sun":{"open":"10:00","close":"18:00"}}',
        1, datetime('now'), datetime('now')
    ),
    (
        'Kintai Paris', 'KTPARIS', 'retail', 'Europe/Paris', 'EUR', 'fr',
        '{"min_shift_hours": 6, "break_minutes": 30}',
        '{"mon":{"open":"08:00","close":"20:00"},"tue":{"open":"08:00","close":"20:00"},"wed":{"open":"08:00","close":"20:00"},"thu":{"open":"08:00","close":"20:00"},"fri":{"open":"08:00","close":"20:00"},"sat":{"open":"09:00","close":"18:00"},"sun":null}',
        1, datetime('now'), datetime('now')
    );
