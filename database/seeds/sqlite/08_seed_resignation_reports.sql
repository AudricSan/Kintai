-- Seed: 08 — rapports de démission (SQLite)
PRAGMA foreign_keys = ON;

INSERT OR IGNORE INTO "resignation_reports"
    ("store_id", "user_id", "employee_number", "employee_name",
     "resignation_date", "reason", "resignation_notice", "notes",
     "person_in_charge", "created_by", "created_at", "updated_at")
SELECT 1, u.id, u.employee_code, u.display_name,
       date('now', '-30 days'), 'Démission volontaire', 'Préavis de 2 semaines', NULL,
       'Admin', 1, datetime('now'), datetime('now')
FROM users u
WHERE u.is_active = 0
LIMIT 1;
