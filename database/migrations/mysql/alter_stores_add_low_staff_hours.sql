ALTER TABLE `stores`
ADD COLUMN `low_staff_hour_start` INT NOT NULL DEFAULT -1,
ADD COLUMN `low_staff_hour_end` INT NOT NULL DEFAULT -1;
