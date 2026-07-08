-- Seed: 07 — rapports d'embauche (SQLite)
PRAGMA foreign_keys = ON;

INSERT OR IGNORE INTO "hiring_reports"
    ("store_id", "user_id", "employee_number", "employee_name", "furigana",
     "gender", "tax_classification", "birth_date", "hire_date", "education",
     "postal_code", "address", "phone", "mobile_phone", "email",
     "guarantor_name", "guarantor_phone", "store_name", "hired_by", "notes",
     "created_by", "created_at", "updated_at")
SELECT 1, u.id, u.employee_code, u.display_name, u.furigana,
       u.gender, u.tax_classification, u.birth_date, su.hire_date, u.education,
       u.postal_code, u.address, u.phone, u.mobile_phone, u.email,
       u.guarantor_name, u.guarantor_phone, s.name, su.hired_by, NULL,
       1, datetime('now'), datetime('now')
FROM users u
JOIN store_user su ON su.user_id = u.id
JOIN stores s ON s.id = su.store_id
LIMIT 1;
