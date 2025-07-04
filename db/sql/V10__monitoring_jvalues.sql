ALTER TABLE `monitorings`
CHANGE `values` `jvalues` json NULL ,
CHANGE `monitoring_date` `dt` date NULL ,
CHANGE `title` `name` varchar(255) NULL ;
