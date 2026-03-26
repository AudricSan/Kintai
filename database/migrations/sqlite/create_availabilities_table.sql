PRAGMA foreign_keys = ON;

-- Migration: 006 — availabilities
-- day_of_week: 1=Monday ... 7=Sunday (ISO 8601)
CREATE TABLE IF NOT EXISTS "availabilities" (
    "id"              INTEGER NOT NULL PRIMARY KEY,
    "store_id"        INTEGER NOT NULL,
    "user_id"         INTEGER NOT NULL,
    "day_of_week"     INTEGER NOT NULL CHECK ("day_of_week" BETWEEN 1 AND 7),
    "start_time"      TEXT    NOT NULL,
    "end_time"        TEXT    NOT NULL,
    "is_available"    INTEGER NOT NULL DEFAULT 1,
    "effective_from"  TEXT    NULL DEFAULT NULL,
    "effective_until" TEXT    NULL DEFAULT NULL,
    "created_at"      TEXT    NOT NULL DEFAULT (datetime('now')),
    "updated_at"      TEXT    NULL DEFAULT NULL,
    FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE,
    FOREIGN KEY ("user_id")  REFERENCES "users"  ("id") ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS "idx_avail_user_day"   ON "availabilities" ("user_id",  "day_of_week", "is_available");
CREATE INDEX IF NOT EXISTS "idx_avail_store_user" ON "availabilities" ("store_id", "user_id");
