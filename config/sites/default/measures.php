<?php
use App\Indicator\emissionA;
use App\Indicator\emissionB;
use App\Indicator\emissionC;
use App\Indicator\emission0;

$melabel=[
	["key" => "cost", 
	"label" =>  "Costo per l’azienda al giorno (per tutti i dipendenti) [€]", "field_type" => "number", "description" => "WIP"],
	["key" => "save_company", "label" =>  "Risparmio per l’azienda al giorno (per tutti i dipendenti) [€]", "field_type" => "number", "description" => "WIP"],
	["key" => "save_employee", "label" =>  "Risparmio per il dipendente al giorno [€]", "field_type" => "number", "description" => "WIP"],
];

return [
	'Measures' => [
		1 => [
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di smartworkers", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni di smartworking/anno", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>  function ($series) {
				$a = new emissionA( 
					floatval($series['days']),
					floatval($series['users']),
					floatval($series['distance']),
					// floatval($series['cost']),
					// floatval($series['save_company']),
					// floatval($series['save_employee']),
				);
				return $a->getOutput();
			},
		],

		2 => [
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di dipendenti che effettuano riunioni da remoto", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Numero medio di riunioni da remoto/anno per dipendente", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media dello spostamento sostituito(a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
					floatval($series['days']),
					floatval($series['users']),
					floatval($series['distance']),
					// floatval($series['cost']),
					// floatval($series['save_company']),
					// floatval($series['save_employee']),
				);
				return $a->getOutput();
			},
		],

		3 => [    // sarebbe opportuno fare un calcolo unico per l'intero pilastro - vedi sopra 
			'labels' => array_merge([
				["key" => "users", "label" =>  "Nr di dipendenti che aderiscono al servizio", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento con mezzi sostenibili", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
				["key" => "percent", "label" =>  "Percentuale utenti che aderiscono all'iniziativa (0-1)", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
					floatval($series['days']),
					floatval($series['users'] * floatval($series['percent'])),
					floatval($series['distance']),
					// floatval($series['cost']),
					// floatval($series['save_company']),
					// floatval($series['save_employee']),
					
				);
				return $a->getOutput();
			},
		],

		4 => [    // sarebbe opportuno fare un calcolo unico per l'intero pilastro - vedi sopra 
			'labels' => array_merge([
				["key" => "users", "label" =>  "Nr di dipendenti che aderiscono al servizio", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento con mezzi sostenibili", "field_type" => "number", "description" => "WIP"],
				["key" => "percent", "label" =>  "Percentuale utenti che aderiscono all'iniziativa (0-1)", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
					floatval($series['days']), 
					floatval($series['users'] * floatval($series['percent'])),
					floatval($series['distance']),
					// floatval($series['cost']),
					// floatval($series['save_company']),
					// floatval($series['save_employee']),
				);
				return $a->getOutput();
			},
		],

		5 => [    // sarebbe opportuno fare un calcolo unico per l'intero pilastro - vedi sopra 
			'labels' => array_merge([
				["key" => "users", "label" =>  "Nr di dipendenti che aderiscono al servizio", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento con mezzi sostenibili", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],				
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']), 
						floatval($series['users']),
						floatval($series['distance']),
						// floatval($series['cost']),
						// floatval($series['save_company']),	
						// floatval($series['save_employee']),
				);
				return $a->getOutput();
			},
		],

		6 => [    // sarebbe opportuno fare un calcolo unico per l'intero pilastro - vedi sopra 
			'labels' => array_merge([
				["key" => "users", "label" =>  "Nr di dipendenti che aderiscono al servizio", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento con mezzi sostenibili", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']), 
						floatval($series['users']), 
						floatval($series['distance']),
						// floatval($series['cost']),
						// floatval($series['save_company']),
						// floatval($series['save_employee']),
				);
				return $a->getOutput();
			},
		],

		7 => [    // sarebbe opportuno fare un calcolo unico per i pilastri 3 & 4 perché contiamo molte volte lo stesso impatto
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero utenti delle rastrelliere", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento in bici/piedi", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']),
						floatval($series['users']), 
						floatval($series['distance']),
						// floatval($series['cost']),
						// floatval($series['save_company']),
						// floatval($series['save_employee']),
					);
				return $a->getOutput();
			},
		],

		8 => [    // sarebbe opportuno fare un calcolo unico per i pilastri 3 & 4 perché contiamo molte volte lo stesso impatto
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di utenti interessati a usufruire degli spogliatoi", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento in bici/piedi", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']),
						floatval($series['users']), 
						floatval($series['distance']),
						// floatval($series['cost']),
						// floatval($series['save_company']),
						// floatval($series['save_employee']),
					);
				return $a->getOutput();
			},
		],

		9 => [    // sarebbe opportuno fare un calcolo unico per i pilastri 3 & 4 perché contiamo molte volte lo stesso impatto
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di utenti non a conoscenza delle strutture offerte", "field_type" => "number"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emission0();
				return $a->getOutput();
			},
		],

		10 => [   // sarebbe opportuno fare un calcolo unico per i pilastri 3 & 4 perché contiamo molte volte lo stesso impatto
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di dipendenti che si sposta in bici/piedi", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento in bici/piedi", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']),
						floatval($series['users']), 
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],


		11 => [    // sarebbe opportuno fare un calcolo unico per i pilastri 3 & 4 perché contiamo molte volte lo stesso impatto
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di aderenti all'iniziativa", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento in bici/piedi", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']),
						floatval($series['users']), 
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],

		12 => [    // sarebbe opportuno fare un calcolo unico per i pilastri 3 & 4 perché contiamo molte volte lo stesso impatto
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di dipendenti che ricevono informazioni", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento in bici/piedi", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']),
						floatval($series['users']), // 5% dei dipendenti che ricevono informazioni lasciano l'auto per spostarsi con la bici
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],

		13 => [    // sarebbe opportuno fare un calcolo unico per i pilastri 3 & 4 perché contiamo molte volte lo stesso impatto
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di aderenti al bike-sharing", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento in bici/piedi", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']),
						floatval($series['users']), 
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],

		14 => [    // sarebbe opportuno fare un calcolo unico per i pilastri 3 & 4 perché contiamo molte volte lo stesso impatto
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di dipendenti che usufruiscono del servizio", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento in bici/piedi", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']),
						floatval($series['users']), 
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],

		15 => [    // sarebbe opportuno fare un calcolo unico per i pilastri 3 & 4 perché contiamo molte volte lo stesso impatto
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di dipendenti", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Numeri di eventi/anno", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']),  //alla fine si calcola il CO2 risparmiato grazie alla partecipazione agli eventi
						floatval($series['users']), // si ipotizza che il 5% dei dipendenti partecipa ad ogni evento
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],


		16 => [    // sarebbe opportuno fare un calcolo unico per i pilastri 5 & 6 con l'eccezione della misura navetta aziendale 
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di dipendenti che sono serviti dal TPL per lo spostamento casa-lavoro", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento con il TPL", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']),
						floatval($series['users']), 
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],

		17 => [
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di dipendenti che utilizzano la navetta", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento con la navetta", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
				["key" => "trips", "label" =>  "Numero di viaggi giornalieri della navetta", "field_type" => "number", "description" => "WIP"],
				["key" => "distance-trip", "label" =>  "Km percorsi in ogni viaggio", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionC(
					floatval($series['users']),
					floatval($series['distance']),
					floatval($series['days']),
					floatval($series['trips']),
					floatval($series['distance-trip']),
				);
				return $a->getOutput();
			},
		],

		18 => [    // sarebbe opportuno fare un calcolo unico per i pilastri 5 & 6 con l'eccezione della misura navetta aziendale 
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di dipendenti che sono serviti dal TPL per lo spostamento casa-lavoro", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento con il TPL", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']),
						floatval($series['users']), 
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],

		19 => [    // sarebbe opportuno fare un calcolo unico per i pilastri 5 & 6 con l'eccezione della misura navetta aziendale 
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di dipendenti che acquistano l'abbonamento annuale", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento con il TPL", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']),
						floatval($series['users']),
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],

		20 => [    // sarebbe opportuno fare un calcolo unico per i pilastri 5 & 6 con l'eccezione della misura navetta aziendale 
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di dipendenti che aderisce alle attività di promozione", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento con il TPL", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']),
						floatval($series['users']),
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],

		21 => [
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di carpoolers", "field_type" => "number", "description" => "WIP"],
				["key" => "days_cp", "label" =>  "Giorni/settimana di carpooling", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],				
				["key" => "equipaggi", "label" =>  "Numero di equipaggi di car pooling", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionB(
					floatval($series['days_cp']  * 48), //48 settimane lavorative all'anno considerando 4 settimane al mese
					floatval($series['distance']),
					floatval($series['users']),					
					floatval($series['distance']),	//Distanza media percorsa da ogni equipaggio
					floatval($series['equipaggi']) / floatval($series['users']) 	//Numero di viaggi al giorno che fa l'equipaggio in sharing rispetto a prima
				);
				return $a->getOutput();
			},
		],

		22 => [
			'labels' => array_merge([
				["key" => "incentivo", "label" =>  "Ammontare dell'incentivo", "field_type" => "number", "description" => "WIP"],
				["key" => "soglia", "label" =>  "Numero di utenti per veicolo necessario per beneficiare dell'incentivo", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emission0();
				return $a->getOutput();
			},
		],

		23 => [ //uguale alla misura 2 - rischio di calcolare l'impatto 2 volte
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero medio/anno di trasferte aziendali", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "% delle trasferte che viene effetuata con mezzi sostenibili", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media dello spostamento sostituito (a/r)", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']), // bisogna modificare la % in numero
						floatval($series['users']),
						floatval($series['distance']),
						);
				return $a->getOutput();
			},
		],

		24 => [ //uguale alla misura 2 - rischio di calcolare l'impatto 2 volte
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di dipendenti che effettuano riunioni da remoto", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Numero medio di riunioni da remoto/anno per dipendente", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media dello spostamento sostituito (a/r)", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']), 
						floatval($series['users']),
						floatval($series['distance']),
						);
				return $a->getOutput();
			},
		],

		25 => [ //uguale alla misura 2 - rischio di calcolare l'impatto 2 volte
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di dipendenti lascia l'auto grazie alla mancanza di sosta", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento con mezzi sostenibili", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']), 
						floatval($series['users']),
						floatval($series['distance']),
						);
				return $a->getOutput();
			},
		],

		26 => [
			'labels' => array_merge([
				["key" => "distance", "label" =>  "Distanza annua totale percorsa dai veicoli aziendali (km/anno)", "field_type" => "number", "description" => "WIP"],
				["key" => "consumption", "label" =>  "Fattore di consumo del nuovo parco veicolare (l/100km)", "field_type" => "number", "description" => "WIP"],
				["key" => "feCO2", "label" => "Fattore di emissione di CO2 dei nuovi veicoli (g/km)", "field_type" => "number", "description" => "WIP" ],
				["key" => "feCO", "label" => "Fattore di emissione di CO dei nuovi veicoli (g/km)", "field_type" => "number", "description" => "WIP" ],
				["key" => "feNOx", "label" => "Fattore di emissione di NOx dei nuovi veicoli (g/km)", "field_type" => "number", "description" => "WIP" ],
				["key" => "fePM10", "label" => "Fattore di emissione di PM10 dei nuovi veicoli (g/km)", "field_type" => "number", "description" => "WIP" ],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionC(
					1,
					floatval($series['distance']),
					floatval($series['distance']),
					floatval($series['consumption']),
					floatval($series['feCO2']),
					floatval($series['feCO']),
					floatval($series['feNOx']),
					floatval($series['fePM10']),
				);
				return $a->getOutput();
			},
		],

		27 => [
			'labels' => array_merge([
				["key" => "distance", "label" =>  "Distanza annua totale percorsa dai veicoli aziendali", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionC(
						1, 
						floatval($series['distance']),
						floatval($series['distance']),
						0.0, 0.0, 0.0, 0.0, 0.0
					);
				return $a->getOutput();
			},
		],

		28 => [    // sarebbe opportuno fare un calcolo unico per i pilastri 3 & 4 perché contiamo molte volte lo stesso impatto
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di aderenti al bike-sharing", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento in bici/piedi", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']),
						floatval($series['users']), 
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],

		29 => [    // sarebbe opportuno fare un calcolo unico per i pilastri 3 & 4 perché contiamo molte volte lo stesso impatto
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di dipendenti incentivati", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" => "Numero totale di km da percorrere in bici", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA(
						1,
						floatval($series['users']), 
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],

		30 => [    // sarebbe opportuno fare un calcolo unico per i pilastri 3 & 4 perché contiamo molte volte lo stesso impatto
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di aderenti al bike-sharing", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento in bici/piedi", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']),
						floatval($series['users']), 
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],

		31 => [    // uguale alla misura 3 
			'labels' => array_merge([
				["key" => "users", "label" =>  "Nr di dipendenti che aderiscono al servizio", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento con mezzi sostenibili", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']), 
						floatval($series['users']), // si ipotizza che il 3% dei dipendenti che aderiscono all'iniziativa si spostano i mezzi sostenibili per alcuni giorni all'anno
						floatval($series['distance'])
				);
				return $a->getOutput();
			},
		],

		32 => [    // uguale alla misura 5 
			'labels' => array_merge([
				["key" => "users", "label" =>  "Nr di dipendenti che aderiscono al servizio", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento con mezzi sostenibili", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']), 
						floatval($series['users']),
						floatval($series['distance'])
				);
				return $a->getOutput();
			},
		],

		33 => [
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di aderenti al car-sharing", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento in auto condivisa", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']),
						floatval($series['users']), 
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],

		34 => [    // sarebbe opportuno fare un calcolo unico per i pilastri 3 & 4 perché contiamo molte volte lo stesso impatto
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di dipendenti che usufruiscono degli strumenti", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento in bici/piedi", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']),
						floatval($series['users']), 
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],

		35 => [
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di dipendenti che usufruirebbero di PediBus", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di utilizzo di PediBus", "field_type" => "number", "description" => "WIP"], // 200? son 200 i giorni scolastici
				["key" => "distance", "label" =>  "Distanza media spostamento casa-scuola (a/r)", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']),
						floatval($series['users']), 
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],

		36 => [
			'labels' => array_merge([
				["key" => "rientri", "label" =>  "Numero di rientri garantiti in taxi", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emission0();
				return $a->getOutput();
			},
		],

		37 => [
			'labels' => array_merge([
				["key" => "posteggi", "label" =>  "Numero di posti auto attualmente offerti", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emission0();
				return $a->getOutput();
			},
		],

		38 => [
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di dipendenti che utilizzano il carpooling scolastico", "field_type" => "number", "description" => "WIP"],
				["key" => "days_cp", "label" =>  "Giorni/settimana di carpooling", "field_type" => "number", "description" => "WIP"],
				["key" => "days_nocp", "label" =>  "Giorni/settimana di viaggio individuale", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
				["key" => "equipaggi", "label" =>  "Numero di equipaggi di car pooling", "field_type" => "number", "description" => "WIP"],
			], $melabel),
			'indicator' =>   function ($series) {
				$a = new emissionB(
					floatval($series['days_no_cp'] * 36), //36 settimane lavorative all'anno considerando 4 settimane per 9 mesi
					floatval($series['days_cs'] * 36), //36 settimane lavorative all'anno considerando 4 settimane al mese
					floatval($series['distance']),
					floatval($series['users']),
					floatval($series['distance']),	//Distanza media percorsa da ogni equipaggio
					floatval($series['equipaggi'])
				);
				return $a->getOutput();
			},
		],

		39 => [
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di nuovi utenti in grado di utilizzare la bici", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di utilizzo della bici", "field_type" => "number", "description" => "WIP"], // 200? son 200 i giorni scolastici
				["key" => "distance", "label" =>  "Distanza media spostamento casa-scuola (a/r)", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']),
						floatval($series['users']), 
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],

		40 => [
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di utenti aderenti", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						5,
						floatval($series['users']), 
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],

		41 => [
			'labels' => array_merge([
				["key" => "membri", "label" =>  "Numero di membri assegnati al Mobility Team", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emission0();
				return $a->getOutput();
			},
		],

		42 => [
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di utenti raggiunti dall'iniziativa", "field_type" => "number", "description" => "WIP"],
				["key" => "actual_users", "label" =>  "Frazione di utenti che effettivamente applicheranno cambiamenti ai loro spostamenti", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento (a/r)", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						365,
						floatval($series['users']) * floatval($series['actual_users']), 
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],

		43 => [
			'labels' => array_merge([
				["key" => "days", "label" =>  "Giorni/anno di spostamento", "field_type" => "number", "description" => "WIP"], // 200? son 200 i giorni scolastici
				["key" => "users", "label" =>  "Numero di utenti raggiunti dall'iniziativa", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento (a/r)", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']),
						floatval($series['users']), 
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],

		44 => [
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di nuovi studenti", "field_type" => "number", "description" => "WIP"],
				["key" => "actual_users", "label" =>  "Frazione di nuovi studenti che effettivamente applicheranno cambiamenti ai loro spostamenti", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento (a/r)", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						365,
						floatval($series['users']) * floatval($series['actual_users']), 
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],

		45 => [
			'labels' => array_merge([
				["key" => "days", "label" =>  "Giorni/anno di spostamento", "field_type" => "number", "description" => "WIP"], // 200? son 200 i giorni scolastici
				["key" => "users", "label" =>  "Numero di utenti raggiunti dall'iniziativa", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento (a/r)", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']),
						floatval($series['users']), 
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],

		46 => [
			'labels' => array_merge([
				["key" => "days", "label" =>  "Giorni/anno di spostamento", "field_type" => "number", "description" => "WIP"], // 200? son 200 i giorni scolastici
				["key" => "users", "label" =>  "Numero di utenti raggiunti dall'iniziativa", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento (a/r)", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']),
						floatval($series['users']), 
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],

		47 => [
			'labels' => array_merge([
				["key" => "numero", "label" =>  "Numero di influencers coinvolti", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emission0();
				return $a->getOutput();
			},
		],

		48 => [
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di studenti che utilizzano il BiciBus", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni/anno di spostamento con il BiciBus", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-scuola (a/r)", "field_type" => "number", "description" => "WIP"],
				["key" => "trips", "label" =>  "Numero di viaggi giornalieri verso la scuola", "field_type" => "number", "description" => "WIP"],
				["key" => "distance-trip", "label" =>  "Km percorsi in ogni viaggio", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA(
					floatval($series['users']),
					floatval($series['distance']),
					floatval($series['days']),
					floatval($series['trips']),
					floatval($series['distance-trip']),
				);
				return $a->getOutput();
			},

		],

		49 => [
			'labels' => array_merge([
				["key" => "days", "label" =>  "Giorni/anno di spostamento", "field_type" => "number", "description" => "WIP"], // 200? son 200 i giorni scolastici
				["key" => "users", "label" =>  "Numero di utenti raggiunti dall'iniziativa", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento (a/r)", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>   function ($series) {
				$a = new emissionA( 
						floatval($series['days']),
						floatval($series['users']), 
						floatval($series['distance']),
					);
				return $a->getOutput();
			},
		],

		51 => [
			'labels' => array_merge([
				["key" => "users", "label" =>  "Numero di coworkers", "field_type" => "number", "description" => "WIP"],
				["key" => "days", "label" =>  "Giorni di coworking/anno", "field_type" => "number", "description" => "WIP"],
				["key" => "distance", "label" =>  "Distanza media spostamento casa-lavoro (a/r) [Km]", "field_type" => "number", "description" => "WIP"],
			],$melabel),
			'indicator' =>  function ($series) {
				$a = new emissionA(
					floatval($series['days']),
					floatval($series['users']),
					floatval($series['distance']),
				);
				return $a->getOutput();
			},
		],
	]
];
