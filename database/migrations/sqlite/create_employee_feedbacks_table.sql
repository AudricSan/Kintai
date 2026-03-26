PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS "employee_feedbacks" (
    "id"         INTEGER NOT NULL PRIMARY KEY,
    "store_id"   INTEGER NOT NULL,
    "user_id"    INTEGER NULL     DEFAULT NULL,
    "shift_id"   INTEGER NULL     DEFAULT NULL,
    "category"   TEXT    NOT NULL DEFAULT 'other'
                         CHECK ("category" IN ('shift','schedule','app','other')),
    "rating"     INTEGER NULL     DEFAULT NULL
                         CHECK ("rating" IS NULL OR ("rating" >= 1 AND "rating" <= 5)),
    "message"    TEXT    NOT NULL,
    "anonymous"  INTEGER NOT NULL DEFAULT 0,
    "created_at" TEXT    NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY ("store_id") REFERENCES "stores" ("id") ON DELETE CASCADE,
    FOREIGN KEY ("user_id")  REFERENCES "users"  ("id") ON DELETE SET NULL,
    FOREIGN KEY ("shift_id") REFERENCES "shifts" ("id") ON DELETE SET NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "uq_employee_feedback_shift"
    ON "employee_feedbacks" ("shift_id")
    WHERE "shift_id" IS NOT NULL;

CREATE INDEX IF NOT EXISTS "idx_employee_feedback_store"
    ON "employee_feedbacks" ("store_id", "created_at");
