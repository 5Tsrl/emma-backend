## EMMA
DELETE FROM `questions` WHERE (`id` = '10');
INSERT INTO `questions` (`id`, `name`, `description`, `long_description`, `options`, `type`, `section_id`, `conditions`, `compulsory_answer`) VALUES ('10', 'mezzi-usati', 'Per lo spostamento di andata e ritorno quale mezzo utilizzi in prevalenza nei mesi invernali?', 'Considera il mezzo prevalente quello che utilizzi per percorrere il maggior numero di chilometri', '[\"Piedi\", \"Bus/Tram\", \"Metro\", \"Auto\", \"Moto\", \"Bici\", \"Monopattino\", \"Taxi\", \"Treno\", \"Servizio ferroviario altri vettori (Trenord, Italo, Alta Velocità, ...)\", \"Car sharing\", \"Bike sharing\", \"Moto sharing\", \"Monopattino sharing\", \"Navetta aziendale\", \"Car pooling\"]', 'multiple', '3', '[]', '0');
INSERT INTO `questions` (`id`, `name`, `description`, `long_description`, `options`, `type`, `section_id`, `creator_id`, `compulsory_answer`) VALUES ('474', 'mezzo-prevalente', 'Come ti rechi più frequentemente al lavoro?', '', '[\"Auto\"]', 'single', '1', '7aab5817-8f9f-4a34-91b2-3c22a0c6e3d7', '1');
UPDATE questions SET options = '[\"Elettrico\", \"Euro 0\", \"Euro 1\", \"Euro 2\", \"Euro 3\", \"Euro 4\", \"Euro 5\", \"non lo so\"]' WHERE (id = '342');
UPDATE questions SET options = '[\"Elettrico\", \"Euro 0\", \"Euro 1\", \"Euro 2\", \"Euro 3\", \"Euro 4\", \"Euro 5\", \"non lo so\"]' WHERE (id = '341');

## Mobility48
INSERT INTO `questions` (`id`, `name`, `description`, `long_description`, `options`, `type`, `section_id`, `creator_id`, `compulsory_answer`) VALUES ('351', 'mezzi-usati', 'Per lo spostamento di andata e ritorno quale mezzo utilizzi in prevalenza nei mesi invernali?', 'Considera il mezzo prevalente quello che utilizzi per percorrere il maggior numero di chilometri', '[\"Piedi\", \"Bus/Tram\", \"Metro\", \"Auto\", \"Moto\", \"Bici\", \"Monopattino\", \"Taxi\", \"Treno\", \"Servizio ferroviario altri vettori (Trenord, Italo, Alta Velocità, ...)\", \"Car sharing\", \"Bike sharing\", \"Moto sharing\", \"Monopattino sharing\", \"Navetta aziendale\", \"Car pooling\"]', 'multiple', '1', '7aab5817-8f9f-4a34-91b2-3c22a0c6e3d7', '0');


