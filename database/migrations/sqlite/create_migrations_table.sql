PRAGMA foreign_keys = ON;

-- Migration: 000 — tracking table
CREATE TABLE IF NOT EXISTS "migrations" (
    "id"          INTEGER NOT NULL PRIMARY KEY,
    "migration"   TEXT    NOT NULL UNIQUE,
    "executed_at" TEXT    NOT NULL DEFAULT (datetime('now'))
);
