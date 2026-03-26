PRAGMA foreign_keys = ON;

-- Migration: 005 — shifts
-- pause_minutes / duration_minutes stored as INTEGER.
-- starts_at / ends_at stored as TEXT (ISO 8601 UTC) for conflict detection.
CREATE TABLE IF NOT EXISTS "shifts" (
    "id"               INTEGER NOT NULL PRIMARY KEY,
    "store_id"         INTEGER NOT NULL,
    "user_id"          INTEGER NOT NULL,
    "shift_date"       TEXT    NOT NULL,
    "start_time"       TEXT    NOT NULL,
    "end_time"         TEXT    NOT NULL,
    "shift_type_id"    INTEGER NULL DEFAULT NULL,
    "cross_midnight"   INTEGER NOT NULL DEFAULT 0,
    "pause_minutes"    INTEGER NOT NULL DEFAULT 0,
    "duration_minutes" INTEGER NOT NULL DEFAULT 0,
    "starts_at"        TEXT    NOT NULL,
    "ends_at"          TEXT    NOT NULL,
    "notes"            TEXT    NULL DEFAULT NULL,
    "created_by"       INTEGER NULL DEFAULT NULL,
    "created_at"       TEXT    NOT NULL DEFAULT (datetime('now')),
    "updated_at"       TEXT    NULL DEFAULT NULL,
    "deleted_at"       TEXT    NULL DEFAULT NULL,
    FOREIGN KEY ("store_id")      REFERENCES "stores"      ("id") ON DELETE CASCADE,
    FOREIGN KEY ("user_id")       REFERENCES "users"       ("id") ON DELETE CASCADE,
    FOREIGN KEY ("shift_type_id") REFERENCES "shift_types" ("id") ON DELETE SET NULL,
    FOREIGN KEY ("created_by")    REFERENCES "users"       ("id") ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS "idx_shifts_store_date" ON "shifts" ("store_id", "shift_date",  "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_shifts_user_date"  ON "shifts" ("user_id",  "shift_date",  "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_shifts_user_time"  ON "shifts" ("user_id",  "starts_at",   "ends_at", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_shifts_store_user" ON "shifts" ("store_id", "user_id",     "shift_date");
