PRAGMA foreign_keys = ON;

-- Migration: 008 — shift_swap_requests
-- ENUM replaced by TEXT + CHECK constraint.
CREATE TABLE IF NOT EXISTS "shift_swap_requests" (
    "id"                 INTEGER NOT NULL PRIMARY KEY,
    "store_id"           INTEGER NOT NULL,
    "requester_id"       INTEGER NOT NULL,
    "target_id"          INTEGER NOT NULL,
    "requester_shift_id" INTEGER NOT NULL,
    "target_shift_id"    INTEGER NULL DEFAULT NULL,
    "status"             TEXT    NOT NULL DEFAULT 'pending'
                                 CHECK ("status" IN ('pending','accepted','refused','cancelled')),
    "reason"             TEXT    NULL DEFAULT NULL,
    "reviewed_by"        INTEGER NULL DEFAULT NULL,
    "reviewed_at"        TEXT    NULL DEFAULT NULL,
    "created_at"         TEXT    NOT NULL DEFAULT (datetime('now')),
    "updated_at"         TEXT    NULL DEFAULT NULL,
    FOREIGN KEY ("store_id")           REFERENCES "stores" ("id") ON DELETE CASCADE,
    FOREIGN KEY ("requester_id")       REFERENCES "users"  ("id") ON DELETE CASCADE,
    FOREIGN KEY ("target_id")          REFERENCES "users"  ("id") ON DELETE CASCADE,
    FOREIGN KEY ("requester_shift_id") REFERENCES "shifts" ("id") ON DELETE CASCADE,
    FOREIGN KEY ("target_shift_id")    REFERENCES "shifts" ("id") ON DELETE SET NULL,
    FOREIGN KEY ("reviewed_by")        REFERENCES "users"  ("id") ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS "idx_swap_store_status" ON "shift_swap_requests" ("store_id",     "status");
CREATE INDEX IF NOT EXISTS "idx_swap_requester"    ON "shift_swap_requests" ("requester_id", "status");
CREATE INDEX IF NOT EXISTS "idx_swap_target"       ON "shift_swap_requests" ("target_id",    "status");
