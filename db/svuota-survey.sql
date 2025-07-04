##Script per svuotare le risposte e le survey quando importi i questionari di test
SET foreign_key_checks = 0;
TRUNCATE TABLE `answers`;
TRUNCATE TABLE `questions`;
TRUNCATE TABLE `questions_surveys`;
TRUNCATE TABLE `surveys`;
TRUNCATE TABLE `companies`;


