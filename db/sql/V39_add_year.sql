ALTER TABLE `pscl` ADD COLUMN `year` INT(4) UNSIGNED ZEROFILL;
ALTER TABLE `surveys` ADD COLUMN `year` int(4) UNSIGNED ZEROFILL;
ALTER TABLE `users` ADD COLUMN `years` json;
ALTER TABLE `companies` ADD COLUMN `years` json;
ALTER TABLE `monitorings` ADD COLUMN `pscl_id` int;
ALTER TABLE `offices` ADD COLUMN `years` json;
SET SQL_SAFE_UPDATES = 0;
update surveys set year = year(closing_date);
SET SQL_SAFE_UPDATES = 1;
-- CREATE INDEX idx_office_id ON employees (office_id);
-- CREATE INDEX idx_user_id ON employees (user_id);
-- togliere 2020, 2021, 2022, 2023 e 2024 form surveys.name
-- only 5t
SET SQL_SAFE_UPDATES = 0;
update surveys set name = REPLACE(name, '2020', '');
update surveys set name = REPLACE(name, '2021', '');
update surveys set name = REPLACE(name, '2022', '');
update surveys set name = REPLACE(name, '2023', '');
update surveys set name = REPLACE(name, '2024', ''); 
SET SQL_SAFE_UPDATES = 1;