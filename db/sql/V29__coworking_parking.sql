ALTER TABLE offices 
ADD COLUMN info_coworking JSON NULL AFTER pascal_measures,
ADD COLUMN info_parking JSON NULL DEFAULT NULL AFTER info_coworking,
ADD COLUMN private_coworking TINYINT(1) NULL DEFAULT 0 AFTER info_parking;
