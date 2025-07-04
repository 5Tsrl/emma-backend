-- Adminer 4.8.0 MySQL 8.0.35 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `answers`;
CREATE TABLE `answers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `question_id` int DEFAULT NULL,
  `survey_id` int DEFAULT NULL,
  `user_id` varchar(36) NOT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `answer` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `question_id_survey_id_user_id` (`question_id`,`survey_id`,`user_id`),
  KEY `survey_id_user_id` (`user_id`,`survey_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


SET NAMES utf8mb4;

DROP TABLE IF EXISTS `areas`;
CREATE TABLE `areas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `province` varchar(2) DEFAULT NULL,
  `polygon` geometry DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `areas_users`;
CREATE TABLE `areas_users` (
  `user_id` char(36) NOT NULL,
  `area_id` int NOT NULL,
  PRIMARY KEY (`user_id`,`area_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `articles`;
CREATE TABLE `articles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `slug` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `published` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `destination_id` int DEFAULT NULL,
  `archived` tinyint(1) DEFAULT '0',
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `promoted` tinyint(1) NOT NULL DEFAULT '0',
  `slider` tinyint(1) DEFAULT '0',
  `keywords` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `url_canonical` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `articles_tags`;
CREATE TABLE `articles_tags` (
  `article_id` int NOT NULL,
  `tag_id` int NOT NULL,
  PRIMARY KEY (`article_id`,`tag_id`),
  KEY `tag_key` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


DROP TABLE IF EXISTS `companies`;
CREATE TABLE `companies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `num_employees` int unsigned DEFAULT NULL,
  `ateco` varchar(20) DEFAULT NULL,
  `company_code` varchar(45) DEFAULT NULL,
  `web` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `city` varchar(45) DEFAULT NULL,
  `province` varchar(2) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `pec` varchar(255) DEFAULT NULL,
  `tel` varchar(45) DEFAULT NULL,
  `type` int NOT NULL DEFAULT '1',
  `survey` json DEFAULT NULL,
  `emissions` json DEFAULT NULL,
  `partner` varchar(45) DEFAULT NULL,
  `additional_data` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `companies_chk_1` CHECK (json_valid(`survey`))
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


DROP TABLE IF EXISTS `company_survey_history`;
CREATE TABLE `company_survey_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `type` varchar(50) NOT NULL,
  `answer` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `company_survey_history_chk_1` CHECK (json_valid(`answer`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `company_types`;
CREATE TABLE `company_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `survey_template` varchar(50) NOT NULL,
  `icon` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `destinations`;
CREATE TABLE `destinations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(250) DEFAULT NULL,
  `slug` varchar(250) DEFAULT NULL,
  `show_in_list` tinyint(1) NOT NULL DEFAULT '1',
  `facebook` varchar(255) DEFAULT NULL,
  `instagram` varchar(255) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lc` varchar(255) DEFAULT NULL,
  `ef` varchar(255) DEFAULT NULL,
  `anno_attivazione` int DEFAULT NULL,
  `comuni` text,
  `tipologia` varchar(255) DEFAULT NULL,
  `presidente` varchar(255) DEFAULT NULL,
  `coach` varchar(255) DEFAULT NULL,
  `fondazione_locale` varchar(255) DEFAULT NULL,
  `descrizione` text,
  `email` varchar(255) DEFAULT NULL,
  `chiuso` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


DROP TABLE IF EXISTS `email_queue`;
CREATE TABLE `email_queue` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `email` varchar(129) NOT NULL,
  `from_name` varchar(255) DEFAULT NULL,
  `from_email` varchar(255) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `config` varchar(30) NOT NULL,
  `template` varchar(50) NOT NULL,
  `layout` varchar(50) NOT NULL,
  `theme` varchar(50) NOT NULL,
  `format` varchar(5) NOT NULL,
  `template_vars` text NOT NULL,
  `headers` text,
  `sent` tinyint(1) NOT NULL DEFAULT '0',
  `locked` tinyint(1) NOT NULL DEFAULT '0',
  `send_tries` int NOT NULL DEFAULT '0',
  `send_at` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` datetime DEFAULT NULL,
  `attachments` text,
  `campaign_id` int DEFAULT NULL,
  `error` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `email_queue_phinxlog`;
CREATE TABLE `email_queue_phinxlog` (
  `version` bigint NOT NULL,
  `migration_name` varchar(100) DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `employees`;
CREATE TABLE `employees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` char(36) DEFAULT NULL,
  `office_id` int DEFAULT NULL,
  `role_description` varchar(45) DEFAULT NULL,
  `orario` varchar(45) DEFAULT NULL,
  `gender` char(1) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `shift` tinyint(1) DEFAULT '0',
  `origin_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `description` text,
  `organizer_email` varchar(255) DEFAULT NULL,
  `max_pax` int DEFAULT NULL,
  `place` varchar(255) DEFAULT NULL,
  `destination_id` int DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `min_year` int DEFAULT NULL,
  `max_year` int DEFAULT NULL,
  `created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


DROP TABLE IF EXISTS `i18n`;
CREATE TABLE `i18n` (
  `id` int NOT NULL AUTO_INCREMENT,
  `locale` varchar(6) NOT NULL,
  `model` varchar(255) NOT NULL,
  `foreign_key` int NOT NULL,
  `field` varchar(255) NOT NULL,
  `content` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `I18N_LOCALE_FIELD` (`locale`,`model`,`foreign_key`,`field`),
  KEY `I18N_FIELD` (`model`,`foreign_key`,`field`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `measures`;
CREATE TABLE `measures` (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) DEFAULT NULL,
  `pillar_id` int DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text,
  `img` varchar(255) DEFAULT NULL,
  `target` enum('scuola','azienda','pascal') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `type` int DEFAULT NULL,
  `service_url` varchar(255) NOT NULL,
  `labels` json DEFAULT NULL,
  `indicator` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `measures` (`id`, `slug`, `pillar_id`, `name`, `description`, `img`, `target`, `type`, `service_url`, `labels`, `indicator`) VALUES
(1,	'smart-working',	1,	'Smart working',	'Facilitare e consentire lo smart working, cioè dare la possibilità ai dipendenti di svolgere           le attività di lavoro lontano dalla sede lavorativa, es. casa.',	'measures/smart-working.jpg',	'azienda',	1,	'',	NULL,	NULL),
(2,	'riunioni-remoto',	1,	'Riunioni da remoto',	'I dipendenti possono effettuare riunioni da remoto con clienti oppure colleghi di sedi remote           grazie a strumenti che permettono le chiamate in conferenza, condivisione di schermo, ecc.',	'measures/riunioni-remoto.jpg',	'azienda',	1,	'',	NULL,	NULL),
(3,	'spesa-domicilio',	2,	'Spesa a domicilio o in azienda',	'Il dipendente riceve la spesa a domicilio o in azienda, senza costi aggiuntivi. Il dipendente            può evitare di venire al lavoro in auto, perchè può portare ogni giorno una piccola quantità di spesa in            bici o in autobus.',	'measures/spesa.jpeg',	'azienda',	1,	'',	NULL,	NULL),
(4,	'farmacia',	2,	'Farmacia in azienda',	'Il dipendente riceve i prodotti di farmacia, es. parafarmaci, integratori, ecc., in azienda,           senza costi aggiuntivi. Il servizio non prevede l\'ordine e consegna di prodotti con obbligo di prescrizione           medica secondo quanto previsto dalla normativa.',	'measures/farmacia.jpg',	'azienda',	1,	'',	NULL,	NULL),
(5,	'lavanderia-azienda',	2,	'Lavanderia a domicilio o in azienda',	'Il dipendente consegna e riceve gli indumenti a domicilio o in azienda, senza costi aggiuntivi.',	'measures/tintoria.jpg',	'azienda',	1,	'',	NULL,	NULL),
(6,	'asilo-aziendale',	2,	'Asilo aziendale',	'Il dipendente ha a disposizione presso la sede lavorativa uno spazio sicuro, vicino e           comodo dove poter lasciare i bambini tra i 3 e i 36 mesi durante l’orario di lavoro. Il dipendente           può evitare di venire al lavoro in auto, perchè non deve deviare durante il suo tragitto casa-lavoro per           portare il suo bambino.',	'measures/asilo-nido.jpg',	'azienda',	1,	'',	NULL,	NULL),
(7,	'posteggi-bici',	3,	'Posteggi per biciclette',	'Il dipendente che si sposta in bici non si preoccupa della sua bici mentre in ufficio           perché l\'azienda offre dei posteggi sicuri e comodi.',	'measures/posteggi-bici.jpg',	'azienda',	1,	'',	NULL,	NULL),
(8,	'spogliatoi',	3,	'Spogliatoi',	'Il dipendente che vuole spostarsi a piedi o in bici non si preoccupa della possibilità di sudare             durante il tragitto casa-lavoro perché presso la sede lavorativa sono disponibili degli             spogliatoi dove ci si può cambiare comodamente.',	'measures/spogliatoio.jpg',	'azienda',	1,	'',	NULL,	NULL),
(9,	'segnaletica',	3,	'Segnaletica',	'Tutte le infrastrutture per i ciclisti presso la sede (es. posteggi, spogliatoi) sono           segnalate in modo chiaro, evidente ed accattivante.',	'measures/segnaletica-bici.jpg',	'azienda',	1,	'',	NULL,	NULL),
(10,	'accessibilita',	3,	'Accessibilità dell\'azienda per pedoni e bici',	'L\'azienda si assicura che sia accessibile per pedoni e ciclisti, valutando lo stato di           infrastrutture per loro intorno alla sede e dialoghando con gli enti pubblici di competenza per           risolvere eventuali problemi.',	'measures/accessibilita.jpg',	'azienda',	1,	'',	NULL,	NULL),
(11,	'incentivi-economici',	4,	'Incentivi economici per pedoni e ciclisti',	'L\'azienda promuove gli spostamenti casa-lavoro a piedi e in bici offrendo incentivi economici,           perché riconosce i numerosi vantaggi sia per essa sia per i dipendenti. Gli incentivi possono avere diverse          forme, es. rimborso km in denaro, raccolta punti, ecc.',	'measures/incentivi-economici.jpg',	'azienda',	1,	'',	NULL,	NULL),
(12,	'informazione-bici-piedi',	4,	'Attività di informazione',	'Il dipendente riceve comunicazioni e materiale informativo (es. mappe della rete ciclabile)           che mette in evidenza l\'accessibilità dell\'azienda a piedi e in bici, vantaggi e incentivi, infrastrutture           disponibili, ecc.',	'measures/informazioni.jpg',	'azienda',	1,	'',	NULL,	NULL),
(13,	'bike-sharing',	4,	'Servizi di bike sharing',	'Anche il dipendente che non possiede una bici oppure non gli è possibile effettuare l\'intero spostamento casa-lavoro in bici è facilitato e incoraggiato a utilizzare questa modalità di trasporto grazie al bike sharing. L\'azienda concorda con servizi di bike sharing esistenti offerte particolari per i suoi dipendenti.',	'measures/bikesharing.jpg',	'azienda',	1,	'',	NULL,	NULL),
(14,	'bici-aziendali',	4,	'Bici aziendali',	'Il dipendente può avere in comodato d’uso bici aziendali (tradizionali oppure elettriche)           che può utilizzare per lo spostamento casa-lavoro. Le bici aziendali possono essere utilizzate anche per           gli spostamenti all’interno della sede.',	'measures/bici-aziendali.jpg',	'azienda',	1,	'',	NULL,	NULL),
(15,	'promozione-bici-piedi',	4,	'Promozione degli spostamenti a piedi e in bici',	'L\'azienda promuove gli spostamenti a piedi e in bici partecipando ad iniziative locali e nazionali           (es. bike to work) ma anche organizzando eventi aziendali.',	'measures/promozione-bici.jpg',	'azienda',	1,	'',	NULL,	NULL),
(16,	'tpl-servizio',	5,	'Trasporto pubblico conveniente e sicuro',	'L’azienda si assicura che il servizio del trasporto pubblico presente intorno alla sede corrisponde           alle esigenze dei suoi dipendenti (es. orari, frequenza, sicurezza) e collabora con gli enti pubblici e gli           operatori del trasporto pubblico per riportare eventuali migliorie.',	'measures/rete-tpl.jpg',	'azienda',	1,	'',	NULL,	NULL),
(17,	'navetta',	5,	'Navetta aziendale',	'L\'azienda organizza un servizio di navetta che offre un collegamento comodo e veloce per i suoi           dipendenti tra nodi importanti di trasporto pubblico (es. stazione ferroviaria, stazione della metropolitana)           e la sede lavorativa, dove non disponibile.',	'measures/navetta.jpg',	'azienda',	1,	'',	NULL,	NULL),
(18,	'informazione-tpl',	5,	'Attività di informazione',	'Il dipendente può accedere facilmente a tutte le informazioni utili (es. linee, orari, fermate)           per pianificare il suo spostamento casa-lavoro con il trasporto pubblico.',	'measures/informazioni.jpg',	'azienda',	1,	'',	NULL,	NULL),
(19,	'incentivo-abbonamento',	6,	'Incentivo per l’acquisto di abbonamenti annuali',	'Il dipendente gode di uno sconto per l’acquisto dell’abbonamento annuale per il trasporto pubblico,           grazie all’incentivo aziendale.',	'measures/tessera-abbonamento.jpg',	'azienda',	1,	'',	NULL,	NULL),
(20,	'promozione-tpl',	6,	'Promozione del trasporto pubblico',	'L’azienda realizza attività di comunicazione per incoraggiare l’utilizzo del trasporto pubblico e azioni             che permettono ai dipendenti di provarlo, es. offerta di biglietti/abbonamenti, comunicazione per             mostrare i costi del trasporto pubblico vs auto privata, per sfatare i falsi miti (sicurezza, bassa qualità             di servizio), ecc.',	'measures/tpl.jpg',	'azienda',	1,	'',	NULL,	NULL),
(21,	'carpooling',	7,	'Facilitare il carpooling',	'I dipendenti possono facilmente organizzarci in equipaggi di carpooling grazie agli strumenti messi           a disposizione dall\'azienda.',	'measures/carpooling.jpg',	'azienda',	1,	'',	NULL,	NULL),
(22,	'carpooling-incentivi',	7,	'Incentivi per chi fa carpooling',	'L\'azienda incoraggia il carpooling offrendo incentivi economici e non, es. rimborso km, posteggi           dedicati, ecc.',	'measures/posteggio-carpooling.jpg',	'azienda',	1,	'',	NULL,	NULL),
(23,	'policy-multimodalita',	8,	'Policy aziendale per la sostituzione dell\'auto con mezzi alternativi',	'L\'azienda diminuisce l\'uso dell\'auto per gli spostamenti per lavoro adottando policy che           promuovono l\'utilizzo di mezzi alternativi e la multimodalità (es. treno + car sharing).',	'measures/multimodalita.jpg',	'azienda',	1,	'',	NULL,	NULL),
(24,	'meno-trasferte',	8,	'Ridurre la necessità di trasferte',	'I dipendenti possono effettuare riunioni da remoto con clienti oppure colleghi di sedi remote           grazie a strumenti che permettono le chiamate in conferenza, condivisione di schermo, ecc.',	'measures/riunioni-remoto.jpg',	'azienda',	1,	'',	NULL,	NULL),
(25,	'posteggi-auto',	9,	'Pianificazione della sosta',	'L\'utilizzo dei posteggi auto disponibili presso la sede viene pianificata, adottando meccanismi           equi e trasparenti, in modo che disincentiva l’utilizzo dell’auto e incentiva l’uso dei mezzi alternativi.',	'measures/posteggi-auto.jpg',	'azienda',	1,	'/services/parking',	NULL,	NULL),
(26,	'parco-veicolare',	10,	'Sostituzione del parco veicolare',	'Valutare la sostituzione del parco veicolare con un sistema di mobilità condivisa a più alta           efficienza (es. sistema ibrido/elettrico/car sharing).',	'measures/parco-veicolare.jpg',	'azienda',	1,	'',	NULL,	NULL),
(27,	'auto-elettiche',	10,	'Auto di servizio elettriche',	'Installare in azienda alcune colonnine di ricarica per l\'auto elettrica e trasformare alcuni dei mezzi in flotta             (auto di pool, auto per gli spostamenti di servizio) in auto elettriche. Queste auto possono essere brandizzate             con il logo aziendale. Si raccomanda di avere a disposizione un fornitore di energia verde ed una applicazione             per il monitoraggio dei consumi e degli utilizzi.             <br>Le auto possono essere prese a noleggio dal proprio operatore NLT a tariffe confrontabili con quelle regolari.',	'measures/auto-elettrica.jpg',	'azienda',	1,	'',	NULL,	NULL),
(33,	'cinque',	7,	'Car sharing',	'Una convenzione con il  car sharing CinQue può permettere di diminuire la flotta aziendale e ridurre i relativi costi.',	'servizi/cinque-logo.jpg',	'azienda',	2,	'https://www.carsharingcinque.it/',	NULL,	NULL),
(34,	'strumenti-manutenzione',	4,	'Strumenti per la manutenzione delle bici',	'L\'azienda dispone spazi e strumenti per la manutenzione delle bici (es. pompe d\'aria).',	'measures/manutenzione-bici.jpg',	'azienda',	1,	'',	NULL,	NULL),
(35,	'pedibus',	4,	'PediBus',	'Organizzare e promuovere il PediBus per lo spostamento casa-scuola. Con il PediBus i bambini vanno e tronano dalla scuola             insieme, camminando lungo un percorso prestabilito e accompagnati da adulti volontari (es. genitori, nonni, insegnanti).',	'measures/pedibus.jpg',	'scuola',	1,	'',	NULL,	NULL),
(36,	'carpooling-taxi',	7,	'Ritorno a casa garantito',	'I dipendenti che fanno carpooling possono utilizzare gratuitamente il taxi oppure il car sharing             (per un numero/budget limitato a mese) per tornare a casa in caso di emergenza (es. straordinario non previsto,             emergenza di salute, ecc.).',	'measures/taxi.jpg',	'azienda',	1,	'',	NULL,	NULL),
(37,	'cortili-senza-auto',	9,	'Cortili senza auto',	'Limitare oppure vietare totalmente la sosta di auto presso il cortile della scuola per dare spazio ai bambini, e disincentivare             gli spostamenti in auto per gli insegnanti.',	'measures/cortili-car-free.jpg',	'scuola',	1,	'',	NULL,	NULL),
(38,	'carpooling-scolastico',	7,	'Carpooling scolastico',	'Quando la scuola è distante dalla casa e non si può spostare in bici o con i mezzi pubblici, si può promuovere la condivisione             dell’auto per lo spostamento casa-scuola. Si può favorire l’organizzazione autonoma delle famiglie in equipaggi di carpooling             predisponendo una bacheca all’ingresso della scuola dove insegnanti e genitori possono offrire o cercare un passaggio.',	'measures/carpooling-scolastico.jpg',	'scuola',	1,	'',	NULL,	NULL),
(39,	'rastrelliere-scuola',	3,	'Rastrelliere dentro in cortile',	'Nel cortile della scuola sono presenti rastrelliere che permettono di posteggiare le bici in sicurezza.',	'measures/rastrelliere.jpg',	'scuola',	1,	'',	NULL,	NULL),
(40,	'settimana-mobilità',	4,	'Settimana della mobilità sostenibile',	'Realizzare almeno una volta all’anno la settimana di mobilità sostenibile. L’evento potrebbe includere attività             in aula e fuori, con lo scopo di promuovere gli spostamenti con mezzi sostenibili. Durante l\'intera settimana si             invita gli studenti a venire a scuola con i mezzi pubblici, in bici o a piedi. L’evento potrebbe essere realizzato             in concomitanza con la Settimana Europea della Mobilità.',	'measures/bimbo-cammina.jpg',	'scuola',	1,	'',	NULL,	NULL),
(41,	'mobility-team',	4,	'Mobility Team',	'Creare il Mobility Team della scuola, costituito dal Mobility Manager Scolastico, l\'amministrazione della scuola e alcuni             insegnanti. Lo scopo del Team è l\'individuazione delle azioni e dei progetti orientati ad una mobilità casa-scuola sostenibile             e sicura e la loro migliore attuazione. Il Mobility Managemer Scolastico ha il ruolo centrale nella pianificazione e realizzazione             delle azioni e in questa attività viene assistito dal Mobility Team.',	'measures/mobility-team.jpg',	'scuola',	1,	'',	NULL,	NULL),
(42,	'formazione',	4,	'Formazione nelle aule',	'Realizzare attività in aula che permettono di analizzare e approfondire il tema della mobilità sostenibile, es. ragionare sui             problemi causati dalla mobilità, perché la mobilità sostenibile sia la soluzione, come si spostano gli studenti e perché,             come potrbbero migliorare il loro comportamento ecc.',	'measures/formazione.jpg',	'scuola',	1,	'',	NULL,	NULL),
(43,	'percorsi-sicuri',	4,	'Percorsi casa-scuola sicuri',	'In base alla residenza degli studenti, individuare dei percorsi sicuri per lo spostamento casa-scuola che gli studenti             potrebbero fare a piedi o in bici. Se necessario collaborare con gli enti locali per la realizzazione di misure di             moderazione di traffico, es. zone 30, Zone Residenziali a Traffico Moderato.',	'measures/percorsi-sicuri.jpg',	'scuola',	1,	'',	NULL,	NULL),
(44,	'nuovi-studenti',	4,	'Settimana di benvenuto per i nuovi studenti',	'Settimana di benvenuto per i nuovi studenti durante la quale ricevono informazioni sulle loro opzioni per spostarsi             con modalità sostenibili per raggiungere la scuola. ',	'measures/nuovi-studenti.jpg',	'scuola',	1,	'',	NULL,	NULL),
(45,	'strade-scolastiche',	4,	'Strade scolastiche',	'Collaborare con gli enti locali per l\'implementazione di misure di moderazione del traffico sulla strada davanti alla scuola,             es. divieto di accesso delle auto all\'orario di entrata e uscita dalla scuola, zona 30, ecc.',	'measures/car-free-street.jpg',	'scuola',	1,	'',	NULL,	NULL),
(46,	'corsi-bici',	4,	'Corsi sulla ciclabilità e sicurezza stradale',	'In collaborazione con enti e associazioni locali, es. polizia stradale, associazioni ciclistiche, realizzare corsi             di sicurezza stradale e mobilità ciclabile, es. muoversi in sicurezza, andare in bici, manutenzione di bici, ecc.',	'measures/corsi-bici.jpg',	'scuola',	1,	'',	NULL,	NULL),
(47,	'influencer',	4,	'Team di influencer per la mobilità sostenibile',	'Creare un team di studenti che sviluppa e carica materiale sui social media, es. Instragram, YouTube, Facebook,             per la promozione della mobilità sostenibile e anche delle relative azioni scolastiche.',	'measures/influencer.jpg',	'scuola',	1,	'',	NULL,	NULL),
(48,	'bicibus',	4,	'BiciBus',	'Organizzare e promuovere il BiciBus per lo spostamento casa-scuola. Gli studenti si spostano in gruppo verso e da scuola in             bicicletta accompagnati da adulti volontari (es. genitori, nonni, insegnanti), lungo percorsi prestabiliti, messi in sicurezza,             e segnalati per essere facilmente individuabili da bambini e automobilisti.',	'measures/bicibus.jpg',	'scuola',	1,	'',	NULL,	NULL),
(51,	'coworking',	1,	'Condividere Spazi (Coworking)',	'Offrire una sala riunioni ad altri lavoratori della rete, oppure permettere ai propri lavoratori di operare da una sede vicino a casa propria. Meno stress, meno km percorsi, meno inquinamento.',	'measures/yolk-coworking-krakow-ceKFL76RBcA-unsplash.jpg',	'azienda',	2,	'/services/coworking',	NULL,	NULL),
(52,	'vincoli-ambientali',	1,	'Vincoli Ambientali',	NULL,	NULL,	'pascal',	1,	'',	NULL,	NULL),
(53,	'vincoli-paesaggistici',	1,	'Vincoli Paesaggistici',	NULL,	NULL,	'pascal',	1,	'',	NULL,	NULL),
(54,	'vincoli-archeologici',	1,	'Vincoli Archeologici',	NULL,	NULL,	'pascal',	1,	'',	NULL,	NULL),
(55,	'vincoli-idrogeologici',	1,	'Vincoli Irdogeologici',	NULL,	NULL,	'pascal',	1,	'',	NULL,	NULL),
(56,	'vincoli-altro',	1,	'Altri Vincoli',	NULL,	NULL,	'pascal',	1,	'',	NULL,	NULL),
(57,	'put',	2,	'Piano Urbano del Traffico (PUT)',	'Riportare gli estremi deli atti amministrativi di approvazione',	'/measures/rete-tpl.jpg',	'pascal',	1,	'',	NULL,	NULL),
(58,	'pum',	2,	'Piano Urbano della Mobilità (PUM)',	'Riportare gli estremi deli atti amministrativi di approvazione',	'/measures/tpl.jpg',	'pascal',	1,	'',	NULL,	NULL),
(59,	'pums',	2,	'Piano Urbano della Mobilità Sostenibile (PUMS)',	'Riportare gli estremi deli atti amministrativi di approvazione',	'/measures/car-free-street.jpg',	'pascal',	1,	'',	NULL,	NULL),
(60,	'paes',	2,	'Piano d\'Azione per l\'energia sostenibile (PAES)',	'Riportare gli estremi deli atti amministrativi di approvazione',	'/measures/cortili-car-free.jpg',	'pascal',	1,	'',	NULL,	NULL),
(61,	'q-aria',	2,	'Accordi di programma/Accordi territoriali per il miglioramento della qualità dell\'aria',	'Riportare gli estremi deli atti amministrativi di approvazione',	'',	'pascal',	1,	'',	NULL,	NULL),
(62,	'circolazione',	2,	'Misure di regolamentazione della circolazione',	'Riportare gli estremi deli atti amministrativi di approvazione',	'/measures/multimodalita.jpg',	'pascal',	1,	'',	NULL,	NULL),
(63,	'progetto-eu',	2,	'Progetto EU',	'Riportare gli estremi deli atti amministrativi di approvazione',	'',	'pascal',	1,	'',	NULL,	NULL),
(64,	'moma-area',	3,	'Mobility Management d\'Area',	'Riportare gli estremi deli atti amministrativi di approvazione',	'/measures/accessibilita.jpg',	'pascal',	1,	'',	NULL,	NULL),
(65,	'moma-azienda',	3,	'Mobility Manager Aziendali',	NULL,	'/measures/posteggi-bici.jpg',	'pascal',	1,	'',	NULL,	NULL),
(66,	'moma-scuola',	3,	'Mobility Manager Scolastici',	NULL,	'/measures/spogliatoio.jpg',	'pascal',	1,	'',	NULL,	NULL),
(67,	'pedibus',	4,	'Percorsi Pedibus',	NULL,	'/measures/pedibus.jpg',	'pascal',	1,	'',	NULL,	NULL),
(68,	'car-sharing',	4,	'Car Sharing',	NULL,	'/measures/posteggi-auto.jpg',	'pascal',	1,	'',	NULL,	NULL),
(69,	'car-pooling',	4,	'Car pooling',	NULL,	'/measures/posteggio-carpooling.jpg',	'pascal',	1,	'',	NULL,	NULL),
(70,	'bike-sharing',	4,	'Bike Sharing',	NULL,	'/measures/bike-sharing.jpg',	'pascal',	1,	'',	NULL,	NULL),
(71,	'velostazioni',	4,	'Velostazioni',	NULL,	'/measures/bici-aziendali.jpg',	'pascal',	1,	'',	NULL,	NULL),
(72,	'scooter-sharing',	4,	'Scooter Sharing',	NULL,	NULL,	'pascal',	1,	'',	NULL,	NULL),
(73,	'infomobilita',	4,	'Infomobilità',	NULL,	'/measures/informazioni.jpg',	'pascal',	1,	'',	NULL,	NULL),
(74,	'mobilita-condivisa',	4,	'Altri servizi di mobilità condivisa',	NULL,	NULL,	'pascal',	1,	'',	NULL,	NULL),
(75,	'piste-ciclabili',	4,	'Piste Ciclabili',	NULL,	'/measures/segnaletica-bici.jpg',	'pascal',	1,	'',	NULL,	NULL),
(76,	'percorsi-pedonali',	4,	'Percorsi Pedonali',	NULL,	'/measures/accessibilita.jpg',	'pascal',	1,	'',	NULL,	NULL),
(77,	'zone-30',	4,	'Zone 30',	NULL,	NULL,	'pascal',	1,	'',	NULL,	NULL),
(78,	'uscite-didattiche',	4,	'Uscite Didattiche',	NULL,	NULL,	'pascal',	1,	'',	NULL,	NULL),
(79,	'spostamenti-lavoro',	4,	'Spostamenti per lavoro',	NULL,	NULL,	'pascal',	1,	'',	NULL,	NULL),
(80,	'formazione',	4,	'Formazione',	NULL,	NULL,	'pascal',	1,	'',	NULL,	NULL),
(81,	'riduzione-traffico',	4,	'Programmi di riduzione del Traffico',	NULL,	NULL,	'pascal',	1,	'',	NULL,	NULL),
(82,	'buoni-mobilita',	4,	'Buoni Mobilità',	NULL,	NULL,	'pascal',	1,	'',	NULL,	NULL),
(83,	'altro',	4,	'Altro',	NULL,	NULL,	'pascal',	1,	'',	NULL,	NULL);

DROP TABLE IF EXISTS `moma_phinxlog`;
CREATE TABLE `moma_phinxlog` (
  `version` bigint NOT NULL,
  `migration_name` varchar(100) DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `monitorings`;
CREATE TABLE `monitorings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `dt` date DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `measure_id` int DEFAULT NULL,
  `office_id` int DEFAULT NULL,
  `jvalues` json DEFAULT NULL,
  `objective` tinyint(1) DEFAULT NULL,
  `survey_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `office_id` (`office_id`),
  CONSTRAINT `monitorings_ibfk_1` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `channel` varchar(30) NOT NULL,
  `type` varchar(30) NOT NULL,
  `created` datetime DEFAULT CURRENT_TIMESTAMP,
  `delivered_at` datetime DEFAULT NULL,
  `errors` text,
  `participant_id` varchar(36) DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `from_mail` varchar(255) DEFAULT NULL,
  `to_mail` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `template` varchar(255) DEFAULT NULL,
  `payload` longtext,
  PRIMARY KEY (`id`),
  KEY `channel` (`channel`),
  KEY `type` (`type`),
  KEY `participant` (`participant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


DROP TABLE IF EXISTS `offices`;
CREATE TABLE `offices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `cap` varchar(6) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `province` varchar(2) DEFAULT NULL,
  `company_id` int DEFAULT '0',
  `lat` double DEFAULT NULL,
  `lon` double DEFAULT NULL,
  `company_code` varchar(45) DEFAULT NULL,
  `office_code` varchar(45) DEFAULT NULL,
  `extended_name` varchar(255) DEFAULT NULL,
  `office_type` varchar(255) DEFAULT NULL,
  `office_code_external` varchar(255) DEFAULT NULL,
  `office_type_extra` varchar(45) DEFAULT NULL,
  `survey` json DEFAULT NULL,
  `label_survey` json DEFAULT NULL,
  `num_employees` int DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `PSCL` json DEFAULT NULL,
  `coworking` tinyint(1) DEFAULT '0',
  `pascal_measures` json DEFAULT NULL,
  `info_coworking` json DEFAULT NULL,
  `info_parking` json DEFAULT NULL,
  `private_coworking` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  CONSTRAINT `offices_chk_1` CHECK (json_valid(`survey`)),
  CONSTRAINT `offices_chk_2` CHECK (json_valid(`label_survey`))
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


DROP TABLE IF EXISTS `origins`;
CREATE TABLE `origins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `address` varchar(100) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `province` char(2) DEFAULT NULL,
  `lat` float DEFAULT NULL,
  `lon` float DEFAULT NULL,
  `company_id` int DEFAULT NULL,
  `survey_id` int DEFAULT NULL,
  `user_id` varchar(36) DEFAULT NULL,
  `created` datetime DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT CURRENT_TIMESTAMP,
  `geocoded_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `survey_id` (`survey_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


DROP TABLE IF EXISTS `participants`;
CREATE TABLE `participants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `surname` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `tel` varchar(50) DEFAULT NULL,
  `privacy` tinyint NOT NULL DEFAULT '1',
  `dob` date DEFAULT NULL,
  `diet` varchar(50) DEFAULT NULL,
  `note` text,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `event_id` int NOT NULL,
  `destination_id` int NOT NULL,
  `pob` varchar(255) NOT NULL,
  `city` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `facebook` varchar(255) NOT NULL,
  `ente` varchar(255) NOT NULL,
  `forum_id_prima_scelta` int NOT NULL,
  `forum_id_seconda_scelta` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


DROP TABLE IF EXISTS `phinxlog`;
CREATE TABLE `phinxlog` (
  `version` bigint NOT NULL,
  `migration_name` varchar(100) DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `pillars`;
CREATE TABLE `pillars` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `description` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `pillars` (`id`, `name`, `description`) VALUES
(1,	'Ridurre la necessità degli spostamenti',	'Diminuire la necessità degli spostamenti casa-lavoro e per lavoro tramite misure e           strumenti che offrono ai dipendenti maggiore flessibilità nella scelta degli spazi di lavoro e la           possibilità di effettuare riunioni da remoto.'),
(2,	'Ridurre necessità complementari',	'Ridurre la necessità di quelli spostamenti che sono “accessori” allo spostamento principale casa-lavoro, tipicamente: accompagnare i figli a scuola, passare al supermercato,  andare in palestra dopo l\'ufficio, ecc.'),
(3,	'Migliorare le infrastrutture per ciclisti e pedoni',	'Rendere sicuri e facilitare gli spostamenti casa-lavoro in bici e a piedi migliorando le infrastrutture presso la sede lavorativa e dialogando con le autorità locali per cambiamenti a livello urbano.'),
(4,	'Rendere più interessante pedalare e camminare',	'Introdurre incentivi che rendono più appetibili gli spostamenti casa-lavoro in bici e a piedi.'),
(5,	'Migliorare la qualità del trasporto pubblico',	'Promuovere miglioramenti del servizio esistente in collaborazione con enti pubblici e operatori del trasporto pubblico.'),
(6,	'Rendere più interessante il trasporto pubblico',	'Introdurre incentivi e/o disincentivi che rendono più appetibile il trasporto pubblico.'),
(7,	'Promuovere l\'uso condiviso dell\'auto',	'Facilitare e incentivare l\'uso condiviso dell\'auto (carpooling) per gli spostamenti casa-lavoro'),
(8,	'Ridurre l\'uso dell\'auto per gli spostamenti per lavoro',	'Introdurre misure e strumenti che offrono alternative all\'utilizzo dell\'auto per gli spostamenti per lavoro oppure permettono di diminuirli.'),
(9,	'Pianificare i posteggi',	'Pianificare i posteggi disponibili presso la sede lavorativa in linea con la strategia aziendale di Mobility Management.'),
(10,	'Rendere più efficiente il parco veicolare',	'Introdurre misure che rendono più efficiente il parco veicolare sia in termini di numero di auto sia in termini di consumi ed emissioni.');

DROP TABLE IF EXISTS `pscl`;
CREATE TABLE `pscl` (
  `id` int NOT NULL,
  `version_tag` varchar(45) DEFAULT NULL,
  `company_id` int DEFAULT NULL,
  `office_id` int DEFAULT NULL,
  `survey_id` int DEFAULT NULL,
  `plan` json DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `questions`;
CREATE TABLE `questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(20) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `options` json DEFAULT NULL COMMENT 'Json contenente {id, label} - es {id: 23, label: ''meno di 23 minuti''}',
  `type` char(10) DEFAULT 'single' COMMENT 'single, multiple, string, int',
  `section_id` int DEFAULT NULL,
  `creator_id` varchar(36) DEFAULT NULL,
  `conditions` json DEFAULT NULL,
  `compulsory_answer` tinyint(1) DEFAULT '0',
  `long_description` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `section_id` (`section_id`),
  CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


DROP TABLE IF EXISTS `questions_surveys`;
CREATE TABLE `questions_surveys` (
  `id` int NOT NULL AUTO_INCREMENT,
  `survey_id` int NOT NULL,
  `question_id` int NOT NULL,
  `weight` int DEFAULT NULL,
  `section_id` int DEFAULT NULL,
  `compulsory` tinyint(1) NOT NULL DEFAULT '0',
  `hidden` tinyint(1) NOT NULL DEFAULT '0',
  `description` varchar(255) DEFAULT NULL,
  `long_description` json DEFAULT NULL,
  `options` json DEFAULT NULL,
  `conditions` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `survey_id` (`survey_id`),
  KEY `question_id` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


DROP TABLE IF EXISTS `reports`;
CREATE TABLE `reports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `q` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_UNIQUE` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `reports` (`id`, `name`, `q`) VALUES
(11,	'coIndicator',	'select \n	sum(km_percorsi.answer*giorni_sede.giorni_sede*2*44*0.7853/1000) as emissioni_co_kg_anno, #44 settimane lavorative/anno consideranto 220 giorni lavorativi/anno\n	sum(km_percorsi.answer*giorni_sede.giorni_sede*2*44*163.0846/1000) as  emissioni_co2_kg_anno,# la constante 163.0846 sono le emissioni di CO2/km\n	sum(km_percorsi.answer*giorni_sede.giorni_sede*2*44*0.4256/1000) as  emissioni_nox_kg_anno,\n	sum(km_percorsi.answer*giorni_sede.giorni_sede*2*44*0.0297/1000) as  emissioni_pm10_kg_anno\n# TAB 1\nfrom (select user_id, answer \nFROM answers\nwhere question_id=3) as km_percorsi,\n# TAB 2 \n(select user_id,\n	CASE\n		WHEN answer = \'\"Sette\"\' THEN 7\n		WHEN answer = \'\"Sei\"\' THEN 6\n		WHEN answer = \'\"Cinque\"\' THEN 5\n		WHEN answer = \'\"Quattro\"\' THEN 4\n		WHEN answer = \'\"Tre\"\' THEN 3\n		WHEN answer = \'\"Due\"\' THEN 2\n		WHEN answer = \'\"Uno\"\' THEN 1\n		ELSE 0\n	END as giorni_sede\nFROM answers\nwhere question_id=194) as  giorni_sede,\n# TAB 3 \n(select answer , user_id\nFROM answers\nwhere question_id=10) as mezzo,\n# TAB 4 \n(select answer , user_id\nFROM answers\nwhere question_id=16) as carburante\n# WHERE\nwhere km_percorsi.user_id = giorni_sede.user_id\nand km_percorsi.user_id = mezzo.user_id\nand km_percorsi.user_id = carburante.user_id\n');

DROP TABLE IF EXISTS `sections`;
CREATE TABLE `sections` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `weight` int DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `sections` (`id`, `name`, `weight`) VALUES
(1,	'Come vieni al lavoro?',	-1),
(2,	'Il tuo spostamento',	20),
(3,	'I tuoi mezzi',	1),
(4,	'Le tue scelte',	30),
(5,	'Il tuo lavoro',	40),
(6,	'Dati Personali',	50),
(7,	'Osservazioni e Suggerimenti',	60);

DROP TABLE IF EXISTS `social_accounts`;
CREATE TABLE `social_accounts` (
  `id` char(36) NOT NULL,
  `user_id` char(36) NOT NULL,
  `provider` varchar(255) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `reference` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `description` text,
  `link` varchar(255) DEFAULT '#',
  `token` varchar(500) NOT NULL,
  `token_secret` varchar(500) DEFAULT NULL,
  `token_expires` datetime DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `data` text NOT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `social_accounts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


DROP TABLE IF EXISTS `subscriptions`;
CREATE TABLE `subscriptions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `subscription` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `user_id` varchar(36) DEFAULT NULL,
  `company_id` int DEFAULT NULL,
  `status_id` int DEFAULT '0',
  `note` text,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `subscriptions_chk_1` CHECK (json_valid(`subscription`))
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


DROP TABLE IF EXISTS `survey_delivery_configs`;
CREATE TABLE `survey_delivery_configs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `survey_id` int NOT NULL,
  `days_before_first_reminder` int NOT NULL,
  `days_before_second_reminder` int NOT NULL,
  `invitation_template` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `reminder_template` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `is_active` tinyint NOT NULL DEFAULT '1',
  `invitation_subject` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `reminder_subject` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `sender_name` varchar(100) DEFAULT NULL,
  `sender_email` varchar(255) DEFAULT NULL,
  `mailer_config` varchar(45) DEFAULT 'default',
  `mail_footer` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `survey_participants`;
CREATE TABLE `survey_participants` (
  `id` varchar(50) NOT NULL,
  `survey_id` int NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `survey_completed_at` datetime DEFAULT NULL,
  `invitation_delivered_at` datetime DEFAULT NULL,
  `first_reminder_delivered_at` datetime DEFAULT NULL,
  `second_reminder_delivered_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `surv_pax` (`survey_id`,`user_id`),
  KEY `surveyid` (`survey_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `surveys`;
CREATE TABLE `surveys` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `company_id` int DEFAULT NULL,
  `version_tag` varchar(45) DEFAULT NULL,
  `description` text,
  `date` date DEFAULT NULL,
  `created` datetime DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT CURRENT_TIMESTAMP,
  `user_id` varchar(255) DEFAULT NULL,
  `opening_date` date DEFAULT NULL,
  `closing_date` date DEFAULT NULL,
  `welcome` text,
  `sending_mode` char(1) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `partnership` text,
  `show_translation` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


DROP TABLE IF EXISTS `tags`;
CREATE TABLE `tags` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `title` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


DROP TABLE IF EXISTS `tpl_operators`;
CREATE TABLE `tpl_operators` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` char(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `company_id` int NOT NULL,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `modified` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `union_rollback`;
CREATE TABLE `union_rollback` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date` datetime DEFAULT NULL,
  `remove_question_id` int DEFAULT NULL,
  `destination_question_id` int DEFAULT NULL,
  `questions_survey_id` text,
  `answers_id` text,
  `remove_question` json DEFAULT NULL,
  `name_union_questions` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` char(36) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `token` varchar(255) DEFAULT NULL,
  `token_expires` datetime DEFAULT NULL,
  `api_token` varchar(255) DEFAULT NULL,
  `activation_date` datetime DEFAULT NULL,
  `secret` varchar(32) DEFAULT NULL,
  `secret_verified` tinyint(1) DEFAULT NULL,
  `tos_date` datetime DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `is_superuser` tinyint(1) NOT NULL DEFAULT '0',
  `role` varchar(255) DEFAULT 'user',
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  `additional_data` json DEFAULT NULL,
  `company_id` int DEFAULT NULL,
  `office_id` int DEFAULT NULL,
  `mobile` varchar(255) DEFAULT NULL,
  `cf` varchar(16) DEFAULT NULL,
  `badge_number` varchar(255) DEFAULT NULL,
  `subcompany` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `email` (`email`),
  KEY `office_id` (`office_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


-- 2024-03-13 07:46:31
