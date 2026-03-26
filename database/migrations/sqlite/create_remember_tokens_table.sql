PRAGMA foreign_keys = ON;

-- Migration: 010 — remember_tokens
CREATE TABLE IF NOT EXISTS "remember_tokens" (
    "id"           INTEGER NOT NULL PRIMARY KEY,
    "user_id"      INTEGER NOT NULL,
    "token_hash"   TEXT    NOT NULL UNIQUE,
    "expires_at"   TEXT    NOT NULL,
    "last_used_at" TEXT    NULL DEFAULT NULL,
    "ip_address"   TEXT    NULL DEFAULT NULL,
    "user_agent"   TEXT    NULL DEFAULT NULL,
    "created_at"   TEXT    NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS "idx_remember_user"    ON "remember_tokens" ("user_id");
CREATE INDEX IF NOT EXISTS "idx_remember_expires" ON "remember_tokens" ("expires_at");
