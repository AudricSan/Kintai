-- Migration: ajouter employee_code à users
ALTER TABLE "users" ADD COLUMN "employee_code" TEXT NULL DEFAULT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS "users_employee_code_unique" ON "users" ("employee_code") WHERE "employee_code" IS NOT NULL;
