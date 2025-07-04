CREATE TABLE monitorings (
  `id` INT NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(45) NOT NULL,
  `monitoring_date` DATETIME NULL,
  `created` DATETIME NULL,
  `modified` DATETIME NULL,
  `measure_id` INT NULL,
  `office_id` INT NULL,
  `values` JSON NULL ,
  PRIMARY KEY (`id`),
  FOREIGN KEY (office_id) REFERENCES offices(id));


  
ALTER TABLE `measures` 
ADD COLUMN `inputs` JSON NULL AFTER `service_url`,
ADD COLUMN `indicator` VARCHAR(255) NULL AFTER `inputs`;
