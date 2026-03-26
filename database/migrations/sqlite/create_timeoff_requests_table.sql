PRAGMA foreign_keys = ON;

-- Migration: 007 — timeoff_requests
-- ENUM replaced by TEXT + CHECK constraint.
CREATE TABLE IF NOT EXISTS "timeoff_requests" (
    "id"           INTEGER NOT NULL PRIMARY KEY,
    "store_id"     INTEGER NOT NULL,
    "user_id"      INTEGER NOT NULL,
    "type"         TEXT    NOT NULL DEFAULT 'vacation'
                           CHECK ("type" IN ('vacation','sick','personal','unpaid','other')),
    "start_date"   TEXT    NOT NULL,
    "end_date"     TEXT    NOT NULL,
    "reason"       TEXT    NULL DEFAULT NULL,
    "status"       TEXT    NOT NULL DEFAULT 'pending'
                           CHECK ("status" IN ('pending','approved','refused','cancelled')),
    "reviewed_by"  INTEGER NULL DEFAULT NULL,
    "reviewed_at"  TEXT    NULL DEFAULT NULL,
    "review_notes" TEXT    NULL DEFAULT NULL,
    "created_at"   TEXT    NOT NULL DEFAULT (datetime('now')),
    "updated_at"   TEXT    NULL DEFAULT NULL,
    FOREIGN KEY ("store_id")    REFERENCES "stores" ("id") ON DELETE CASCADE,
    FOREIGN KEY ("user_id")     REFERENCES "users"  ("id") ON DELETE CASCADE,
    FOREIGN KEY ("reviewed_by") REFERENCES "users"  ("id") ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS "idx_timeoff_user_dates"   ON "timeoff_requests" ("user_id",  "start_date", "end_date", "status");
CREATE INDEX IF NOT EXISTS "idx_timeoff_store_status" ON "timeoff_requests" ("store_id", "status",     "start_date");
CREATE INDEX IF NOT EXISTS "idx_timeoff_store_dates"  ON "timeoff_requests" ("store_id", "start_date", "end_date");
