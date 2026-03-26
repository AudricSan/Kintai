-- Migration : Ajouter les limites de durée de shift aux réglages généraux du magasin
ALTER TABLE `stores` 
    ADD COLUMN `min_shift_minutes` INT NOT NULL DEFAULT 120,
    ADD COLUMN `max_shift_minutes` INT NOT NULL DEFAULT 480;
