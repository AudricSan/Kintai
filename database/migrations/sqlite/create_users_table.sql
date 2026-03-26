PRAGMA foreign_keys = ON;

-- Migration: 001 — users
-- updated_at: no ON UPDATE support in SQLite — managed by the application layer.
CREATE TABLE IF NOT EXISTS "users" (
    "id" INTEGER NOT NULL PRIMARY KEY,
    "email" TEXT NOT NULL,
    "password_hash" TEXT NOT NULL,
    "first_name" TEXT NOT NULL,
    "last_name" TEXT NOT NULL,
    "display_name" TEXT NOT NULL,
    "phone" TEXT NULL DEFAULT NULL,
    "profile_image" TEXT NULL DEFAULT NULL,
    "color" TEXT NOT NULL DEFAULT '#3B82F6',
    "is_admin"  INTEGER NOT NULL DEFAULT 0,
    "is_active" INTEGER NOT NULL DEFAULT 1,
    "email_verified_at" TEXT NULL DEFAULT NULL,
    "last_login_at" TEXT NULL DEFAULT NULL,
    "created_at" TEXT NOT NULL DEFAULT(datetime('now')),
    "updated_at" TEXT NULL DEFAULT NULL,
    "deleted_at" TEXT NULL DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "uk_users_email" ON "users" ("email");

CREATE INDEX IF NOT EXISTS "idx_users_active" ON "users" ("is_active", "deleted_at");

CREATE INDEX IF NOT EXISTS "idx_users_deleted" ON "users" ("deleted_at");