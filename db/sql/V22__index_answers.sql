ALTER TABLE answers 
ADD INDEX `survey_id_user_id` (`user_id` ASC, `survey_id` ASC) VISIBLE;
