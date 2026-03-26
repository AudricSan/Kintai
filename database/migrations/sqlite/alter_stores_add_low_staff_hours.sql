ALTER TABLE "stores"
ADD COLUMN "low_staff_hour_start" INTEGER NOT NULL DEFAULT -1;

ALTER TABLE "stores"
ADD COLUMN "low_staff_hour_end" INTEGER NOT NULL DEFAULT -1;
