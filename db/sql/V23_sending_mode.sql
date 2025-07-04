ALTER TABLE surveys 
ADD COLUMN `sending_mode` CHAR(1) NULL DEFAULT NULL AFTER `welcome`;