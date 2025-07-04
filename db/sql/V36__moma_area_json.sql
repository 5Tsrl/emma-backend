CREATE TABLE `areas` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NULL DEFAULT NULL,
  `city` VARCHAR(255) NULL DEFAULT NULL,
  `province` VARCHAR(2) NULL DEFAULT NULL,
  `polygon` GEOMETRY NULL,
  PRIMARY KEY (`id`));

  CREATE TABLE `areas_users` (
  `user_id` CHAR(36) NOT NULL,
  `area_id` INT NOT NULL,
  PRIMARY KEY (`user_id`, `area_id`));