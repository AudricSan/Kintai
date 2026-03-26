-- Migration: ajouter peer_accepted_at à shift_swap_requests
ALTER TABLE `shift_swap_requests` ADD COLUMN `peer_accepted_at` DATETIME NULL DEFAULT NULL AFTER `reason`;
