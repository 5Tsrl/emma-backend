ALTER TABLE `company_types`
ADD COLUMN `icon` varchar(255) DEFAULT NULL;

UPDATE company_types SET icon = 'building-o' WHERE survey_template = 'azienda';
UPDATE company_types SET icon = 'pencil' WHERE survey_template = 'scuola';




UPDATE `questions` SET
`id` = '18',
`name` = 'soddisf-auto-moto',
`description` = ' Esprimi il tuo grado di soddisfazione sui seguenti aspetti del mezzo privato che utilizzi per il tuo spostamento',
`long_description` = NULL,
`options` = '{\"groups\": [{\"label\": \"Costi dello spostamento\", \"options\": [\"Per nulla soddisfattoxxxx\", \"Poco soddisfatto\", \"Abbastanza soddisfatto\", \"Molto soddisfatto\"]}, {\"label\": \"Tempi dello spostamento\", \"options\": [\"Per nulla soddisfatto\", \"Poco soddisfatto\", \"Abbastanza soddisfatto\", \"Molto soddisfatto\"]}, {\"label\": \"Sicurezza stradale durante il tragitto\", \"options\": [\"Per nulla soddisfatto\", \"Poco soddisfatto\", \"Abbastanza soddisfatto\", \"Molto soddisfatto\"]}, {\"label\": \"Sicurezza da furti e vandalismi\", \"options\": [\"Per nulla soddisfatto\", \"Poco soddisfatto\", \"Abbastanza soddisfatto\", \"Molto soddisfatto\"]}, {\"label\": \"Sistema dei parcheggi\", \"options\": [\"Per nulla soddisfatto\", \"Poco soddisfatto\", \"Abbastanza soddisfatto\", \"Molto soddisfatto\"]}], \"allowOther\": false}',
`type` = 'array',
`section_id` = '4',
`creator_id` = NULL,
`conditions` = '{\"value\": [[[[\"Auto\", \"Moto/scooter\"]]]], \"groups\": [\"\"], \"display\": \"conditionally\", \"question\": 10, \"description\": \"Per lo spostamento di andata e ritorno quale mezzo utilizzi in prevalenza? \"}',
`compulsory_answer` = '0'
WHERE `id` = '18';


UPDATE questions SET type = 'array' WHERE id = 21;

UPDATE `questions` SET
`type` = 'array'
WHERE `id` = 37;

UPDATE `questions` SET
`options` = '{\"groups\": [{\"label\": \"Classifica 1\", \"options\": [\"Individuazione di punti d’incontro (fermate indicate con paline, punti di sosta, ecc.)\", \"Iscrizione a servizi esistenti (app, ecc.)\", \"Organizzazione dei gruppi coordinata dall’azienda\"]}, {\"label\": \"Classifica 2\", \"options\": [\"Iscrizione a servizi esistenti (app, ecc.)\", \"Organizzazione dei gruppi coordinata dall’azienda\", \"Individuazione di punti d’incontro (fermate indicate con paline, punti di sosta, ecc.)\"]}, {\"label\": \"Classifica 3\", \"options\": [\"Organizzazione dei gruppi coordinata dall’azienda\", \"Iscrizione a servizi esistenti (app, ecc.)\", \"Individuazione di punti d’incontro (fermate indicate con paline, punti di sosta, ecc.)\"]}], \"allowOther\": false}',
`type` = 'array'
WHERE `id` = '29';

UPDATE `questions` SET
`options` = '{\"groups\": [{\"label\": \"Autobus/tram\", \"options\": [\"No\", \"N/A\", \"Sì\"]}, {\"label\": \"Metropolitana\", \"options\": [\"No\", \"N/A\", \"Sì\"]}, {\"label\": \"Treno\", \"options\": [\"Sì\", \"N/A\", \"No\"]}, {\"label\": \"Navetta\", \"options\": [\"No\", \"N/A\"]}, {\"label\": \"Altro\", \"options\": []}], \"allowOther\": false}',
`type` = 'array'
WHERE `id` = '24';

UPDATE `questions` SET
`type` = 'array'
WHERE `id` = '26';

UPDATE `questions` SET
`type` = 'array'
WHERE `id` = '18';
UPDATE `questions` SET
`name` = 'spostamento-pranzo'
WHERE `id` = '202';
