ALTER TABLE `companies` 
ADD COLUMN `additional_data` JSON NULL DEFAULT NULL AFTER `partner`;

ALTER TABLE `users` 
CHANGE COLUMN `additional_data` `additional_data` JSON NULL DEFAULT NULL ;
