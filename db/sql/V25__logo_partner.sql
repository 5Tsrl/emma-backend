ALTER TABLE `surveys` 
ADD COLUMN `logo` VARCHAR(255) NULL AFTER `sending_mode`,
ADD COLUMN `partnership` TEXT NULL AFTER `logo`;

ALTER TABLE `survey_delivery_configs` 
ADD COLUMN `mail_footer` VARCHAR(45) NULL AFTER `mailer_config`;
