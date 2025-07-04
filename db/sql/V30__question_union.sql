CREATE TABLE `union_rollback` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `date` DATETIME NULL,
  `remove_question_id` INT NULL,
  `destination_question_id` INT NULL,
  `questions_survey_id` TEXT NULL,
  `answers_id` TEXT NULL,
  `remove_question` JSON NULL,
  `name_union_questions` VARCHAR(200) NULL,
  PRIMARY KEY (`id`));