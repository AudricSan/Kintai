PRAGMA foreign_keys = ON;

-- Migration: 002 — stores
-- break_settings and opening_hours stored as TEXT (JSON serialised by application).
CREATE TABLE IF NOT EXISTS "stores" (
    "id"              INTEGER NOT NULL PRIMARY KEY,
    "name"            TEXT    NOT NULL,
    "code"            TEXT    NOT NULL,
    "type"            TEXT    NOT NULL DEFAULT 'retail',
    "timezone"        TEXT    NOT NULL DEFAULT 'UTC',
    "currency"        TEXT    NOT NULL DEFAULT 'EUR',
    "locale"          TEXT    NOT NULL DEFAULT 'en',
    "address_street"  TEXT    NULL DEFAULT NULL,
    "address_city"    TEXT    NULL DEFAULT NULL,
    "address_postal"  TEXT    NULL DEFAULT NULL,
    "address_country" TEXT    NULL DEFAULT NULL,
    "phone"           TEXT    NULL DEFAULT NULL,
    "email"           TEXT    NULL DEFAULT NULL,
    "break_settings"  TEXT    NOT NULL DEFAULT '{"min_shift_hours": 6, "break_minutes": 60}',
    "opening_hours"   TEXT    NOT NULL DEFAULT '{}',
    "is_active"       INTEGER NOT NULL DEFAULT 1,
    "created_at"      TEXT    NOT NULL DEFAULT (datetime('now')),
    "updated_at"      TEXT    NULL DEFAULT NULL,
    "deleted_at"      TEXT    NULL DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "uk_stores_code"   ON "stores" ("code");
CREATE        INDEX IF NOT EXISTS "idx_stores_active" ON "stores" ("is_active", "deleted_at");
