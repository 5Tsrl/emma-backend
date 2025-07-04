CREATE TABLE `tpl_operators` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` char(20) COLLATE utf8mb4_general_ci NOT NULL,
  `company_id` int NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `modified` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `tpl_operators` (`id`, `name`, `company_id`, `code`, `modified`, `created`) VALUES
(1,	'trenord',	1636,	'1234',	'2021-04-29 12:16:43',	'2021-04-29 12:16:43'),
(2,	'gtt',	1636,	'4533434',	'2021-04-29 12:16:52',	'2021-04-29 12:16:52'),
(3,	'atm',	1636,	'54343a',	'2021-04-29 12:17:07',	'2021-04-29 12:17:07');
