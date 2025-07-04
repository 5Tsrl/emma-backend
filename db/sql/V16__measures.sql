ALTER TABLE `measures`
CHANGE `target` `target` enum('scuola','azienda','pascal') COLLATE 'utf8mb4_0900_ai_ci' NULL AFTER `img`;

UPDATE `company_types` SET
`survey_template` = 'pascal'
WHERE `id` = '10';


INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('vincoli-ambientali', '1', 'Vincoli Ambientali', NULL, NULL, 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('vincoli-paesaggistici', '1', 'Vincoli Paesaggistici', NULL, NULL, 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('vincoli-archeologici', '1', 'Vincoli Archeologici', NULL, NULL, 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('vincoli-idrogeologici', '1', 'Vincoli Irdogeologici', NULL, NULL, 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('vincoli-altro', '1', 'Altri Vincoli', NULL, NULL, 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('put', '2', 'Piano Urbano del Traffico (PUT)', 'Riportare gli estremi deli atti amministrativi di approvazione', '/measures/rete-tpl.jpg', 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('pum', '2', 'Piano Urbano della Mobilità (PUM)', 'Riportare gli estremi deli atti amministrativi di approvazione', '/measures/tpl.jpg', 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('pums', '2', 'Piano Urbano della Mobilità Sostenibile (PUMS)', 'Riportare gli estremi deli atti amministrativi di approvazione', '/measures/car-free-street.jpg', 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('paes', '2', "Piano d'Azione per l'energia sostenibile (PAES)", 'Riportare gli estremi deli atti amministrativi di approvazione', '/measures/cortili-car-free.jpg', 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('q-aria', '2', "Accordi di programma/Accordi territoriali per il miglioramento della qualità dell'aria", 'Riportare gli estremi deli atti amministrativi di approvazione', '', 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('circolazione', '2', "Misure di regolamentazione della circolazione", 'Riportare gli estremi deli atti amministrativi di approvazione', '/measures/multimodalita.jpg', 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('progetto-eu', '2', "Progetto EU", 'Riportare gli estremi deli atti amministrativi di approvazione', '', 3, '1', '', NULL, NULL);
    /**** Mobility Management*/
INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('moma-area', '3', "Mobility Management d'Area", 'Riportare gli estremi deli atti amministrativi di approvazione', '/measures/accessibilita.jpg', 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('moma-azienda', '3', "Mobility Manager Aziendali", NULL , '/measures/posteggi-bici.jpg', 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('moma-scuola', '3', "Mobility Manager Scolastici", NULL, '/measures/spogliatoio.jpg', 3, '1', '', NULL, NULL);
/**** Azioni */
INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('pedibus', '4', 'Percorsi Pedibus', NULL, '/measures/pedibus.jpg', 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('car-sharing', '4', 'Car Sharing', NULL, '/measures/posteggi-auto.jpg', 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('car-pooling', '4', 'Car pooling', NULL, '/measures/posteggio-carpooling.jpg', 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('bike-sharing', '4', 'Bike Sharing', NULL, '/measures/bike-sharing.jpg', 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('velostazioni', '4', 'Velostazioni', NULL, '/measures/bici-aziendali.jpg', 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('scooter-sharing', '4', 'Scooter Sharing', NULL, NULL, 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('infomobilita', '4', 'Infomobilità', NULL, '/measures/informazioni.jpg', 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('mobilita-condivisa', '4', 'Altri servizi di mobilità condivisa', NULL, NULL, 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('piste-ciclabili', '4', 'Piste Ciclabili', NULL, '/measures/segnaletica-bici.jpg', 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('percorsi-pedonali', '4', 'Percorsi Pedonali', NULL, '/measures/accessibilita.jpg', 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('zone-30', '4', 'Zone 30', NULL, NULL, 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('uscite-didattiche', '4', 'Uscite Didattiche', NULL, NULL, 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('spostamenti-lavoro', '4', 'Spostamenti per lavoro', NULL, NULL, 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('formazione', '4', 'Formazione', NULL, NULL, 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('riduzione-traffico', '4', 'Programmi di riduzione del Traffico', NULL, NULL, 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('buoni-mobilita', '4', 'Buoni Mobilità', NULL, NULL, 3, '1', '', NULL, NULL);

INSERT INTO `measures` (`slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`)
VALUES ('altro', '4', 'Altro', NULL, NULL, 3, '1', '', NULL, NULL);