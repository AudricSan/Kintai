PRAGMA foreign_keys = ON;

-- Migration: 011 — sessions
-- user_id is a soft reference: no FK constraint to allow anonymous sessions.
-- last_activity stored as INTEGER (Unix timestamp).
CREATE TABLE IF NOT EXISTS "sessions" (
    "id"            TEXT    NOT NULL PRIMARY KEY,
    "user_id"       INTEGER NULL DEFAULT NULL,
    "ip_address"    TEXT    NULL DEFAULT NULL,
    "user_agent"    TEXT    NULL DEFAULT NULL,
    "payload"       TEXT    NOT NULL,
    "last_activity" INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS "idx_sessions_user"     ON "sessions" ("user_id");
CREATE INDEX IF NOT EXISTS "idx_sessions_activity" ON "sessions" ("last_activity");
