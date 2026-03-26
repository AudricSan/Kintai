-- Migration: alter_shifts — ajout colonnes salaire estimé
PRAGMA foreign_keys = ON;

ALTER TABLE "shifts" ADD COLUMN "estimated_salary" REAL    NULL DEFAULT NULL;
ALTER TABLE "shifts" ADD COLUMN "wage_breakdown"   TEXT    NULL DEFAULT NULL;
