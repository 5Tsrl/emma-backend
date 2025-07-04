ALTER TABLE `survey_delivery_configs` 
ADD COLUMN `mailer_config` VARCHAR(45) NULL DEFAULT 'default' AFTER `sender_email`;
