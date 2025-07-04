<?php
use App\Indicator\emissionA;
use App\Indicator\emissionB;
use App\Indicator\emissionC;
use App\Indicator\emission0;

return [
	'PascalMunicipalityType' => 10,

	'pascalPillars' => [
		["id" => 1, "name" => "Presenza di vincoli", "description" => ""],
		["id" => 2, "name" => "Pianificazione dei trasporti", "description" => ""],
		["id" => 3, "name" => "Mobility Management", "description" => ""],
		["id" => 4, "name" => "Mobility Management", "description" => ""]
	],

	'PascalMeasures' => [
		52 => [
			'labels' => [
				["key" => "vincoli", "label" =>  "Presenza Vincoli Ambientali", "field_type" => "radio"],
				["key" => "superficie", "label" =>  "Sup. interessata (mq)", "field_type" => "number"],
				["key" => "note", "label" =>  "Note", "field_type" => "text"],
			],
			'indicator' =>  function ($series) {
				return null;
			},
		],
		53 => [
			'labels' => [
				["key" => "vincoli", "label" =>  "Presenza Vincoli Paesaggistici", "field_type" => "radio"],
				["key" => "superficie", "label" =>  "Sup. interessata (mq)", "field_type" => "number"],
				["key" => "note", "label" =>  "Note", "field_type" => "text"],
			],
			'indicator' =>  function ($series) {
				return null;
			},
		],
		54 => [
			'labels' => [
				["key" => "vincoli", "label" =>  "Presenza Vincoli Archeologici", "field_type" => "radio"],
				["key" => "superficie", "label" =>  "Sup. interessata (mq)", "field_type" => "number"],
				["key" => "note", "label" =>  "Note", "field_type" => "text"],
			],
			'indicator' =>  function ($series) {
				return null;
			},
		],
		55 => [
			'labels' => [
				["key" => "vincoli", "label" =>  "Presenza Vincoli Idrogeologici", "field_type" => "radio"],
				["key" => "superficie", "label" =>  "Sup. interessata (mq)", "field_type" => "number"],
				["key" => "note", "label" =>  "Note", "field_type" => "text"],
			],
			'indicator' =>  function ($series) {
				return null;
			},
		],
		56 => [
			'labels' => [
				["key" => "vincoli", "label" =>  "Presenza Altri Vincoli", "field_type" => "radio"],
				["key" => "altro", "label" => "Specificare", "field_type" => "text"],
				["key" => "superficie", "label" =>  "Sup. interessata (mq)", "field_type" => "number"],
				["key" => "note", "label" =>  "Note", "field_type" => "text"],
			],
			'indicator' =>  function ($series) {
				return null;
			},
		],
		57 => [
			'labels' => [
				["key" => "Yes", "label" =>  "", "field_type" => "radio"],
				["key" => "Num", "label" => "Num.", "field_type" => "number"],
				["key" => "dt", "label" =>  "Data", "field_type" => "date"],
			],
			'indicator' =>  function ($series) {
				return null;
			},
		],
		58 => [
			'labels' => [
				["key" => "Yes", "label" =>  "", "field_type" => "radio"],
				["key" => "Num", "label" => "Num.", "field_type" => "number"],
				["key" => "dt", "label" =>  "Data", "field_type" => "date"],
			],
			'indicator' =>  function ($series) {
				return null;
			},
		],
		59 => [
			'labels' => [
				["key" => "Yes", "label" =>  "", "field_type" => "radio"],
				["key" => "Num", "label" => "Num.", "field_type" => "number"],
				["key" => "dt", "label" =>  "Data", "field_type" => "date"],
			],
			'indicator' =>  function ($series) {
				return null;
			},
		],
		60 => [
			'labels' => [
				["key" => "Yes", "label" =>  "", "field_type" => "radio"],
				["key" => "Num", "label" => "Num.", "field_type" => "number"],
				["key" => "dt", "label" =>  "Data", "field_type" => "date"],
			],
			'indicator' =>  function ($series) {
				return null;
			},
		],
		61 => [
			'labels' => [
				["key" => "Yes", "label" =>  "", "field_type" => "radio"],
				["key" => "Num", "label" => "Num.", "field_type" => "number"],
				["key" => "dt", "label" =>  "Data", "field_type" => "date"],
			],
			'indicator' =>  function ($series) {
				return null;
			},
		],
		62 => [
			'labels' => [
				["key" => "Yes", "label" =>  "", "field_type" => "radio"],
				["key" => "Num", "label" => "Num.", "field_type" => "number"],
				["key" => "dt", "label" =>  "Data", "field_type" => "date"],
			],
			'indicator' =>  function ($series) {
				return null;
			},
		],
		63 => [
			'labels' => [
				["key" => "Yes", "label" =>  "", "field_type" => "radio"],
				["key" => "Num", "label" => "Num.", "field_type" => "number"],
				["key" => "dt", "label" =>  "Data", "field_type" => "date"],
			],
			'indicator' =>  function ($series) {
				return null;
			},
		],
		64 => [
			'labels' => [
				["key" => "Yes", "label" =>  "", "field_type" => "radio"],
				["key" => "Num", "label" => "Num.", "field_type" => "number"],
				["key" => "dt", "label" =>  "Data", "field_type" => "date"],
			],
			'indicator' =>  function ($series) {
				return null;
			},
		],
		65 => [
			'labels' => [
				["key" => "NMM", "label" =>  "Numero Mobility Manager", "field_type" => "number"],
				["key" => "Yes", "label" => "", "field_type" => "radio"],
				["key" => "US", "label" =>  "Utenti serviti", "field_type" => "number"],
			],
			'indicator' =>  function ($series) {
				return null;
			},
		],
		66 => [
			'labels' => [
				["key" => "NMM", "label" =>  "Numero Mobility Manager", "field_type" => "number"],
				["key" => "Yes", "label" => "", "field_type" => "radio"],
				["key" => "SS", "label" =>  "Studenti serviti", "field_type" => "number"],
			],
			'indicator' =>  function ($series) {
				return null;
			},
		],
		67 => [
			'labels' => [
				["key" => "nump", "label" => "Numero Percorsi Previsti", "field_type" => "number"],
				["key" => "ut", "label" => "Numero Medio di alunni coinvolti giornalmente", "field_type" => "number"],
				["key" => "l", "label" => "Lunghezza totale dei percorsi", "field_type" => "number"],
				["key" => "gg", "label" => "Giorni di servizio (stima su base annua)", "field_type" => "number"],
				["key" => "costo", "label" => "Costo previsto (€/anno)", "field_type" => "number"],
			],
			'indicator' =>  function ($series) {
				$a = new emissionA(
					floatval($series['gg']),
					floatval($series['ut']),
					floatval($series['l']) / floatval($series['nump'])
				);
				return $a->getOutput();
			},
		],
		68 => [
			'labels' => [
				["key" => "na", "label" => "Numero auto", "field_type" => "number"],
				["key" => "al", "label" => "Alimentazione", "field_type" => "text"],
				["key" => "kp", "label" => "Km percorsi (stima su base annua per singolo veicolo)", "field_type" => "number"],
				["key" => "cu", "label" => "Costo unitario (€) del veicolo attrezzato per il servizio", "field_type" => "number"],
			],
			'indicator' =>  function ($series) {
				$a = new emissionB(
					365,
					floatval($series['kp']) / 365,
					8,
					8 * floatval($series['kp']) / 365,
					floatval($series['na']) * 8
				);
				return $a->getOutput();
			},
		],
		69 => [
			'labels' => [
				["key" => "nu", "label" => "Numero utenti(stima su base annua)", "field_type" => "number"],
			],
			'indicator' =>  function ($series) {
				$a = new emissionB(
					220,
					floatval($series['nu']) * 25,
					floatval($series['nu']),
					25,
					floatval($series['nu']) / 4
				);
				return $a->getOutput();
			},
		],
		70 => [
			'labels' => [
				["key" => "nc", "label" => "Numero biciclette", "field_type" => "number"],
				["key" => "tp", "label" => "Tipologia", "field_type" => "text"],
				["key" => "kp", "label" => "Km percorsi (stima su base annua per singola bici)", "field_type" => "number"],
				["key" => "cub", "label" => "Costo unitario bici(€)", "field_type" => "number"],
			],
			'indicator' =>  function ($series) {
				$a = new emissionA(
					365, //giorni di attività
					floatval($series['nc']) * 2.8, //numero utenti sottratti all'auto
					3.24 //riduzione km auto (da calcolo complicatissimo di elena pedon, call 4 giugno 2021)
				);
				return $a->getOutput();
			},
		],
		71 => [
			'labels' => [
				["key" => "nv", "label" => "Numero velostazioni", "field_type" => "number"],
				["key" => "nspv", "label" => "Numero stalli per velostazione", "field_type" => "number"],
				["key" => "tps", "label" => "Tipologia stalli", "field_type" => "text"],
				["key" => "cu/s", "label" => "Costo unitario/stallo(€)", "field_type" => "number"],
			],
			'indicator' =>  function ($series) {
				$a = new emissionA(
					365, //giorni di attività
					floatval($series['nspv']) * floatval($series['nv']) * 2.8, //numero utenti sottratti all'auto
					3.24 //riduzione km auto (da calcolo complicatissimo di elena pedon, call 4 giugno 2021)
				);
				return $a->getOutput();
			},
		],
		72 => [
			'labels' => [
				["key" => "ns", "label" => "Numero scooter", "field_type" => "number"],
				["key" => "al", "label" => "Alimentazione", "field_type" => "text"],
				["key" => "kp", "label" => "Km percorsi(stima su base annua per singola scooter)", "field_type" => "number"],
				["key" => "cus", "label" => "Costo unitario scooter(€)", "field_type" => "number"],
			],
			'indicator' =>  function ($series) {
				return null;
			},
		],
		73 => [
			'labels' => [
				["key" => "npi", "label" => "Numero paline informative", "field_type" => "number"],
				["key" => "cu", "label" => "Costo unitario(€)", "field_type" => "number"],
				["key" => "nas", "label" => "Numero applicazioni sviluppate", "field_type" => "number"],
				["key" => "npmv", "label" => "Numero pannelli a messaggio variabile", "field_type" => "number"],
				["key" => "costo", "label" => "Costo unitario(€)", "field_type" => "number"],
			],
			'indicator' =>  function ($series) {
				return null;
			},
		],
		74 => [
			'labels' => [
				["key" => "ns", "label" => "Numero servizi", "field_type" => "number"],
				["key" => "ds", "label" => "Descrizione", "field_type" => "text"],
				["key" => "nu", "label" => "Numero utenti(stima su base annua)", "field_type" => "number"],
				["key" => "costop", "label" => "Costo previsto(€)", "field_type" => "number"],
			],
			'indicator' =>  function ($series) {
				return null;
			},
		],
		75 => [
			'labels' => [
				["key" => "tp", "label" => "Tipologia", "field_type" => "text"],
				["key" => "lt", "label" => "Lunghezza totale(Km)", "field_type" => "number"],
				["key" => "up", "label" => "Utenti potenziali(num, medio, giornaliero)", "field_type" => "number"],
				["key" => "costo", "label" => "Costo(€/Km)", "field_type" => "number"],
			],
			'indicator' =>  function ($series) {
				$a = new emissionA(
					365, //giorni di attività
					floatval($series['up']), //numero utenti sottratti all'auto
					(floatval($series['up']) / 1.2) * 3 //riduzione km auto (le costanti sono definite da Elena 4 giu 2021)
				);
				return $a->getOutput();
			},
		],
		// 24 => [
		//   'labels' => [
		//     ["key" => "tp", "label" => "Tipologia", "field_type" => "text"],
		//     ["key" => "lt", "label" => "Lunghezza totale(Km)", "field_type" => "number"],
		//     ["key" => "up", "label" => "Utenti potenziali(num, medio, giornaliero)", "field_type" => "number"],
		//     ["key" => "costo", "label" => "Costo(€/Km)", "field_type" => "number"],
		//   ],
		//   'indicator' =>  function ($series) {
		//     $a = new emissionA(
		//       365, //giorni di attività
		//       floatval($series['up']), //numero utenti sottratti all'auto
		//       (floatval($series['up']) / 1.2) * 3 //riduzione km auto (le costanti sono definite da Elena 4 giu 2021)
		//     );
		//     return $a->getOutput();
		//   },
		// ],
		76 => [
			'labels' => [
				["key" => "tp", "label" => "Tipologia", "field_type" => "text"],
				["key" => "lt", "label" => "Lunghezza totale(Km)", "field_type" => "number"],
				["key" => "up", "label" => "Utenti potenziali(num, medio, giornaliero)", "field_type" => "number"],
				["key" => "costo", "label" => "Costo(€/Km)", "field_type" => "number"],
			],
			'indicator' =>  function ($series) {
				$a = new emissionA(
					200, //giorni di attività
					floatval($series['up']), //numero utenti sottratti all'auto
					(floatval($series['up']) / 1.2) * 1 //iduzione km auto (da file CALCOLI_EMISSIONI.xls > IIgruppo > riga 44)
				);
				return $a->getOutput();
			},
		],
		77 => [
			'labels' => [
				["key" => "su", "label" => "Superficie(Kmq)", "field_type" => "number"],
				["key" => "lsi", "label" => "Lunghezza strade interessate(Km)", "field_type" => "number"],
				["key" => "ui", "label" => "Utenti interessati", "field_type" => "number"],
			],
			'indicator' =>  function ($series) {
				$a = new emissionA(
					365, //giorni di attività
					floatval($series['ui']), //numero utenti sottratti all'auto
					(floatval($series['ui']) / 1.2) * 0.75 //riduzione km auto (da file CALCOLI_EMISSIONI.xls > IIgruppo > riga 48)
				);
				return $a->getOutput();
			},
		],
		78 => [
			'labels' => [
				["key" => "nudp", "label" => "Numero uscite didattiche programmata(stima su base annua)", "field_type" => "number"],
				["key" => "nup", "label" => "Numero utenti potenziali(stima su base annua)", "field_type" => "number"]
			],
			'indicator' =>  function ($series) {
				$a = new emissionA(
					floatval($series['nudp']), //giorni di attività
					floatval($series['nup']), //numero utenti sottratti all'auto
					(floatval($series['nup']) / 1.2) * 10 //riduzione km auto (da file CALCOLI_EMISSIONI.xls > III_VII gruppo > riga 6)
				);
				return $a->getOutput();
			},
		],
		79 => [
			'labels' => [
				["key" => "nsp", "label" => "Numero spostamenti programmati(stima su base annua)", "field_type" => "number"],
				["key" => "nup", "label" => "Numero utenti potenziali(stima su base annua)", "field_type" => "number"]
			],
			'indicator' =>  function ($series) {
				$a = new emissionA(
					220, //giorni di attività
					floatval($series['nup']), //numero utenti sottratti all'auto
					(floatval($series['nup']) / 1.2) * 5 //riduzione km auto (da file CALCOLI_EMISSIONI.xls > III_VII gruppo > riga 10)
				);
				return $a->getOutput();
			},
		],
		80 => [
			'labels' => [
				["key" => "ncp", "label" => "Numero corsi programmati(stima su base annua)", "field_type" => "number"],
				["key" => "nup", "label" => "Numero allievi/partecipanti potenziali(stima su base annua)", "field_type" => "number"]
			],
			'indicator' =>  function ($series) {
				$a = new emissionA(
					200, //giorni di attività
					floatval($series['nup']), //numero utenti sottratti all'auto
					(floatval($series['nup']) / 1.2) * 1 //riduzione km auto (da file CALCOLI_EMISSIONI.xls > III_VII gruppo > riga 10)
				);
				return $a->getOutput();
			},
		],
		81 => [
			'labels' => [
				["key" => "nv", "label" => "Numero varchi", "field_type" => "number"],
				["key" => "nns", "label" => "Numero nuovi semafori", "field_type" => "number"],
				["key" => "nna", "label" => "Numero nuovi attraversamenti", "field_type" => "number"],
				["key" => "sapi", "label" => "Superficie area pedonale interessata(kmq)", "field_type" => "number"]
			],
			'indicator' =>  function ($series) {
				$a = new emissionA(
					365, //giorni di attività
					250, //numero utenti sottratti all'auto
					(250 / 1.2) * 0.75 //riduzione km auto (da file CALCOLI_EMISSIONI.xls > III_VII gruppo > riga 10)
				);
				return $a->getOutput();
			},
		],
		82 => [
			'labels' => [
				["key" => "nba", "label" => "Numero buoni/anno", "field_type" => "number"],
				["key" => "nup", "label" => "Numero beneficiari/anno", "field_type" => "number"],
				["key" => "rkm", "label" => "Riduzione Km/anno con auto privata", "field_type" => "number"]
			],
			'indicator' =>  function ($series) {
				$a = new emissionA(
					95, //giorni di attività
					floatval($series['nup']), //numero utenti sottratti all'auto
					(floatval($series['nup']) / 1.2) * floatval($series['rkm']) //riduzione km auto (da file CALCOLI_EMISSIONI.xls > III_VII gruppo > riga 34)
				);
				return $a->getOutput();
			},
		],
		83 => [
			'labels' => [
				["key" => "sp", "label" => "Specificare", "field_type" => "text"],
				["key" => "c/um", "label" => "Costo/unita di misura", "field_type" => "number"],
			],
			'indicator' =>  function ($series) {
				return null;
			},
		],
	],
];