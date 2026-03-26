PRAGMA foreign_keys = ON;

-- Migration: 004 — shift_types
-- hourly_rate stored as REAL (SQLite equivalent of DECIMAL).
CREATE TABLE IF NOT EXISTS "shift_types" (
    "id"          INTEGER NOT NULL PRIMARY KEY,
    "store_id"    INTEGER NOT NULL,
    "name"        TEXT    NOT NULL,
    "code"        TEXT    NOT NULL,
    "start_time"  TEXT    NOT NULL,
    "end_time"    TEXT    NOT NULL,
    "hourly_rate" REAL    NOT NULL DEFAULT 0,
    "color"       TEXT    NOT NULL DEFAULT '#3B82F6',
    "sort_order"  INTEGER NOT NULL DEFAULT 0,
    "is_active"   INTEGER NOT NULL DEFAULT 1,
    "created_at"  TEXT    NOT NULL DEFAULT (datetime('now')),
    "updated_at"  TEXT    NULL DEFAULT NULL,
    FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE
);

CREATE UNIQUE INDEX IF NOT EXISTS "uk_shift_type_store_code" ON "shift_types" ("store_id", "code");
CREATE        INDEX IF NOT EXISTS "idx_shift_type_active"    ON "shift_types" ("store_id", "is_active", "sort_order");
