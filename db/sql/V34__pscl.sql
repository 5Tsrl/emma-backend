CREATE TABLE `pscl` (
  `id` INT NOT NULL,
  `version_tag` VARCHAR(45) NULL,
  `company_id` INT NULL,
  `office_id` INT NULL,
  `survey_id` INT NULL,
  `plan` JSON NULL,
  `created` DATETIME NULL,
  `modified` DATETIME NULL,
  PRIMARY KEY (`id`));
