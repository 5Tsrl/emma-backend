-- database Mobility48 
UPDATE answers
SET answer = TRIM(BOTH '"' FROM REPLACE(answer, '\\', ''))
WHERE `question_id` = 302

-- database Emma 
UPDATE answers
SET answer = TRIM(BOTH '"' FROM REPLACE(answer, '\\', ''))
WHERE `question_id` = 340