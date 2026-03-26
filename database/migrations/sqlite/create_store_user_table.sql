PRAGMA foreign_keys = ON;

-- Migration: 003 — store_user (pivot)
-- ENUM replaced by TEXT + CHECK constraint.
-- hourly_rates stored as TEXT (JSON serialised by application).
CREATE TABLE IF NOT EXISTS "store_user" (
    "id"            INTEGER NOT NULL PRIMARY KEY,
    "store_id"      INTEGER NOT NULL,
    "user_id"       INTEGER NOT NULL,
    "role"          TEXT    NOT NULL DEFAULT 'staff'
                            CHECK ("role" IN ('admin', 'manager', 'staff')),
    "staff_code"    TEXT    NULL DEFAULT NULL,
    "hire_date"     TEXT    NULL DEFAULT NULL,
    "contract_type" TEXT    NULL DEFAULT NULL,
    "is_manager"    INTEGER NOT NULL DEFAULT 0,
    "hourly_rates"  TEXT    NULL DEFAULT NULL,
    "is_active"     INTEGER NOT NULL DEFAULT 1,
    "created_at"    TEXT    NOT NULL DEFAULT (datetime('now')),
    "updated_at"    TEXT    NULL DEFAULT NULL,
    FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE,
    FOREIGN KEY ("user_id")  REFERENCES "users"  ("id") ON DELETE CASCADE
);

CREATE UNIQUE INDEX IF NOT EXISTS "uk_store_user"        ON "store_user" ("store_id", "user_id");
CREATE UNIQUE INDEX IF NOT EXISTS "uk_store_staff_code"  ON "store_user" ("store_id", "staff_code");
CREATE        INDEX IF NOT EXISTS "idx_store_user_user"  ON "store_user" ("user_id");
CREATE        INDEX IF NOT EXISTS "idx_store_user_role"  ON "store_user" ("store_id", "role");
