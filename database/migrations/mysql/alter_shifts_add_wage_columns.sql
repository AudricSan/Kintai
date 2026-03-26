-- Migration: alter_shifts — ajout colonnes salaire estimé
ALTER TABLE `shifts`
    ADD COLUMN `estimated_salary` DECIMAL(10,2) NULL DEFAULT NULL AFTER `notes`,
    ADD COLUMN `wage_breakdown`   JSON          NULL DEFAULT NULL AFTER `estimated_salary`;
