ALTER TABLE `questions_surveys` 
ADD COLUMN `description` VARCHAR(255) NULL DEFAULT NULL AFTER `hidden`,
ADD COLUMN `long_description` JSON NULL DEFAULT NULL AFTER `description`,
ADD COLUMN `options` JSON NULL AFTER `long_description`,
ADD COLUMN `conditions` JSON NULL AFTER `options`;
