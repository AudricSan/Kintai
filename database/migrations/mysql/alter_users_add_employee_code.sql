-- Migration: ajouter employee_code à users
ALTER TABLE `users` ADD COLUMN `employee_code` VARCHAR(32) NULL DEFAULT NULL UNIQUE;
