ALTER TABLE `tpl_operators` 
CHANGE COLUMN `name` `name` CHAR(20) CHARACTER SET 'utf8mb4' NOT NULL ,
CHANGE COLUMN `company_id` `company_id` INT NOT NULL ,
CHANGE COLUMN `code` `code` VARCHAR(20) CHARACTER SET 'utf8mb4' NULL DEFAULT NULL ,
CHANGE COLUMN `modified` `modified` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP ,
CHANGE COLUMN `created` `created` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;

INSERT INTO `tpl_operators` (`name`, `company_id`, `code`) VALUES ('gtt', '-1', NULL);
INSERT INTO `tpl_operators` (`name`, `company_id`) VALUES ('atac', '-1');
