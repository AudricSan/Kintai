PRAGMA foreign_keys = ON;

-- Migration: user_shift_type_rates — taux horaire personnalisé par employé et type de shift
CREATE TABLE IF NOT EXISTS "user_shift_type_rates" (
    "id"            INTEGER NOT NULL PRIMARY KEY,
    "user_id"       INTEGER NOT NULL,
    "shift_type_id" INTEGER NOT NULL,
    "hourly_rate"   REAL    NOT NULL,
    "created_at"    TEXT    NOT NULL DEFAULT(datetime('now')),
    "updated_at"    TEXT    NULL DEFAULT NULL,
    FOREIGN KEY ("user_id")       REFERENCES "users"("id")       ON DELETE CASCADE,
    FOREIGN KEY ("shift_type_id") REFERENCES "shift_types"("id") ON DELETE CASCADE
);

CREATE UNIQUE INDEX IF NOT EXISTS "uk_user_shift_type_rates" ON "user_shift_type_rates" ("user_id", "shift_type_id");
CREATE INDEX IF NOT EXISTS "idx_user_shift_type_rates_user" ON "user_shift_type_rates" ("user_id");
