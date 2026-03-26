PRAGMA foreign_keys = ON;

-- Migration: 009 — audit_log
-- Append-only: no updated_at. details stored as TEXT (JSON).
CREATE TABLE IF NOT EXISTS "audit_log" (
    "id"            INTEGER NOT NULL PRIMARY KEY,
    "store_id"      INTEGER NULL DEFAULT NULL,
    "user_id"       INTEGER NULL DEFAULT NULL,
    "action"        TEXT    NOT NULL,
    "resource_type" TEXT    NOT NULL,
    "resource_id"   INTEGER NULL DEFAULT NULL,
    "details"       TEXT    NULL DEFAULT NULL,
    "ip_address"    TEXT    NULL DEFAULT NULL,
    "user_agent"    TEXT    NULL DEFAULT NULL,
    "created_at"    TEXT    NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE SET NULL,
    FOREIGN KEY ("user_id")  REFERENCES "users"  ("id") ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS "idx_audit_store_time" ON "audit_log" ("store_id",      "created_at");
CREATE INDEX IF NOT EXISTS "idx_audit_user_time"  ON "audit_log" ("user_id",       "created_at");
CREATE INDEX IF NOT EXISTS "idx_audit_resource"   ON "audit_log" ("resource_type", "resource_id");
CREATE INDEX IF NOT EXISTS "idx_audit_time"       ON "audit_log" ("created_at");
