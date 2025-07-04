ALTER TABLE `questions` 
ADD COLUMN `long_description_j` JSON NULL AFTER `compulsory_answer`;

update questions set long_description_j =  JSON_OBJECT("description", long_description) 
where long_description is not null and long_description <> "";

ALTER TABLE `questions` 
DROP COLUMN `long_description`;

ALTER TABLE `questions` 
CHANGE COLUMN `long_description_j` `long_description` JSON NULL DEFAULT NULL ;


