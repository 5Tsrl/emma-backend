ALTER TABLE users 
ADD INDEX `email` (`email` ASC) VISIBLE;

ALTER TABLE `survey_participants` 
ADD INDEX `surv_pax` (`survey_id` ASC, `user_id` ASC) VISIBLE,
ADD INDEX `surveyid` (`survey_id` ASC) VISIBLE;
