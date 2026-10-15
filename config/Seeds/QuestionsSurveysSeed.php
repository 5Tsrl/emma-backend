<?php
declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * QuestionsSurveys seed.
 */
class QuestionsSurveysSeed extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeds is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     */
    public function run()
    {
        // Complete questions_surveys seed for survey_id=1866 (changed to survey_id=1)
        // Only including questions that exist in the emma_brescia.questions table
        $data = [
            [
                'survey_id' => 1,
                'question_id' => 31,
                'weight' => -100,
                'section_id' => 1,
                'compulsory' => 0,
                'hidden' => 0,
                'description' => 'In quali giorni della settimana utilizzi o utilizzeresti il car pooling?',
                'long_description' => json_encode([
                    'description' => 'Più risposte possibili'
                ]),
                'options' => json_encode([
                    'Lunedì',
                    'Martedì',
                    'Mercoledì',
                    'Giovedì',
                    'Venerdì',
                    'Sabato',
                    'Domenica'
                ]),
                'conditions' => json_encode([]),
                'compulsory_answer' => 1
            ],
            [
                'survey_id' => 1,
                'question_id' => 36,
                'weight' => -90,
                'section_id' => 1,
                'compulsory' => 0,
                'hidden' => 0,
                'description' => 'a quali condizioni raggiungeresti il luogo di lavoro in bicicletta?',
                'long_description' => json_encode([
                    'description' => ''
                ]),
                'options' => json_encode([
                    'Se ci fossero itinerari ciclabili',
                    'Se in azienda fossero disponibili attrezzature utili allo spostamento in bici  (rastrelliere, tettorie, spogliatoi, docce)',
                    'Se si potesse arrivare fino al luogo di lavoro',
                    'Se esistesse un parcheggio adeguato di interscambio con il TPL (es in stazione)',
                    'Se esistesse un parcheggio adeguato presso il luogo di lavoro',
                    'Se potessi disporre di un incentivo per l\'acquisto di una bicicletta',
                    'Solo nei mesi estivi',
                    'Mai'
                ]),
                'conditions' => json_encode([
                    'value' => [0, 4, 1, 5, 2, 6, 3],
                    'groups' => [''],
                    'display' => 'conditionally',
                    'question' => 31,
                    'description' => 'In quali giorni della settimana utilizzi o utilizzeresti il car pooling?'
                ]),
                'compulsory_answer' => 0
            ],
            [
                'survey_id' => 1,
                'question_id' => 6,
                'weight' => -80,
                'section_id' => 1,
                'compulsory' => 0,
                'hidden' => 0,
                'description' => 'Quanti minuti impieghi, mediamente e comprendendo eventuali soste, per lo SPOSTAMENTO LAVORO-CASA',
                'long_description' => json_encode([
                    'description' => ''
                ]),
                'options' => json_encode([
                    'meno di 15 minuti',
                    'più di 90 minuti',
                    'tra 15 e 30 minuti',
                    'tra 30 e 45 minuti',
                    'tra 45 e 60 minuti',
                    'tra 60 e 75 minuti',
                    'tra 75 e 90 minuti'
                ]),
                'conditions' => null,
                'compulsory_answer' => 0
            ],
            [
                'survey_id' => 1,
                'question_id' => 9,
                'weight' => -70,
                'section_id' => 1,
                'compulsory' => 0,
                'hidden' => 0,
                'description' => 'Durante lo spostamento, in andata o al ritorno, svolgi altre attività (es. commissioni, fare la spesa, fare sport, accompagnare i figli a scuola ecc...)?',
                'long_description' => json_encode([
                    'description' => ''
                ]),
                'options' => json_encode([
                    'Sì, all\'andata',
                    'Sì, al ritorno',
                    'Sì, sia all\'andata che al ritorno',
                    'Sì, solo qualche volta',
                    'No'
                ]),
                'conditions' => null,
                'compulsory_answer' => 1
            ],
            [
                'survey_id' => 1,
                'question_id' => 26,
                'weight' => -100,
                'section_id' => 3,
                'compulsory' => 0,
                'hidden' => 0,
                'description' => 'Quanto ti ritieni soddisfatto dei seguenti aspetti riguardanti lo spostamento con i mezzi pubblici?',
                'long_description' => json_encode([
                    'description' => ''
                ]),
                'options' => json_encode([
                    'groups' => [
                        [
                            'label' => 'Costi dello spostamento',
                            'options' => [
                                'Per nulla soddisfatto',
                                'Poco soddisfatto',
                                'Abbastanza soddisfatto',
                                'Molto soddisfatto'
                            ]
                        ],
                        [
                            'label' => 'Tempi dello spostamento',
                            'options' => [
                                'Per nulla soddisfatto',
                                'Poco soddisfatto',
                                'Abbastanza soddisfatto',
                                'Molto soddisfatto'
                            ]
                        ],
                        [
                            'label' => 'Frequenza corse',
                            'options' => [
                                'Per nulla soddisfatto ',
                                'Poco soddisfatto',
                                'Abbastanza soddisfatto',
                                'Molto soddisfatto'
                            ]
                        ],
                        [
                            'label' => 'Puntualità corse',
                            'options' => [
                                'Per nulla soddisfatto ',
                                'Poco soddisfatto',
                                'Abbastanza soddisfatto',
                                'Molto soddisfatto'
                            ]
                        ],
                        [
                            'label' => 'Numero di interscambi',
                            'options' => [
                                'Per nulla soddisfatto ',
                                'Poco soddisfatto',
                                'Abbastanza soddisfatto',
                                'Molto soddisfatto'
                            ]
                        ],
                        [
                            'label' => 'Comodità del viaggio',
                            'options' => [
                                'Per nulla soddisfatto',
                                'Poco soddisfatto',
                                'Abbastanza soddisfatto',
                                'Molto soddisfatto'
                            ]
                        ],
                        [
                            'label' => 'Sicurezza personale',
                            'options' => [
                                'Per nulla soddisfatto',
                                'Poco soddisfatto',
                                'Abbastanza soddisfatto',
                                'Molto soddisfatto'
                            ]
                        ]
                    ],
                    'allowOther' => true
                ]),
                'conditions' => json_encode([]),
                'compulsory_answer' => 1
            ],
            [
                'survey_id' => 1,
                'question_id' => 340,
                'weight' => -90,
                'section_id' => 11,
                'compulsory' => 0,
                'hidden' => 0,
                'description' => 'Il tuo spostamento su mappa',
                'long_description' => json_encode([
                    'mapqs' => [
                        'en' => [
                            'do' => 'Click on the',
                            'Cap' => 'Postal code',
                            'del' => 'Delete this mode',
                            'back' => 'Back',
                            'city' => 'City',
                            'mean' => 'mode of transportation you use when leaving your home',
                            'next' => 'next',
                            'click' => 'Click here to locate yourself on the map',
                            'meters' => 'for at least 500 meters',
                            'address' => 'Address',
                            'cc_auto' => 'What is your car\'s engine displacement?',
                            'cc_moto' => 'What is your motorcycle or scooter\'s engine displacement?',
                            'province' => 'Province',
                            'research' => 'Research in progress ...',
                            'set_cost' => 'Set a cost greater than 0 and less than 100000.',
                            'amb_class' => 'To which environmental class does your car belong?',
                            'but_descr' => 'Using another mode? Add it',
                            'type_auto' => [
                                'Taxi' => 'Taxi',
                                'Personal' => 'Personal',
                                'Car pooling' => 'Car pooling',
                                'Car sharing' => 'Car sharing',
                                'company_car' => 'Company car'
                            ],
                            'type_bike' => [
                                'Personal' => 'Personal',
                                'Bike sharing' => 'Bike sharing'
                            ],
                            'type_mono' => [
                                'Personal' => 'Personal',
                                'Scooter sharing' => 'Scooter sharing'
                            ],
                            'type_moto' => [
                                'Personal' => 'Personal',
                                'Moto sharing' => 'Moto sharing'
                            ],
                            'insert_Cap' => 'Insert a postal code, e.g. 10036',
                            'mean_label' => 'Mean of Transport',
                            'type_train' => [
                                'Servizio ferroviario Trenitalia/GTT' => 'Trenitalia/GTT railway service',
                                'Servizio ferroviario altri vettori (Trenord, Italo, Alta Velocità, ...)' => 'Railway service by other carriers (Trenord, Italo, High-speed, ...)'
                            ],
                            'which_cost' => 'How much do you spend per month (round trip)?',
                            'car_fueling' => 'What is your car\'s fueling type?',
                            'insert_city' => 'Insert a city, e.g. Settimo Torinese',
                            'only_ci_pro' => 'city and province only.',
                            'privacy_que' => 'Concerns about privacy? You are not providing confidential information. The address is already known by your employer and will only be used for commuting purposes. It will not be disclosed to third parties unless in an anonymous form.',
                            'stepStrings' => [
                                'FIRST',
                                'SECOND',
                                'THIRD',
                                'FOURTH',
                                'FIFTH',
                                'SIXTH',
                                'SEVENTH',
                                'EIGHTH',
                                'NINTH',
                                'TENTH'
                            ],
                            'which_modes' => 'Which modes do you use?',
                            'leavingpoint' => '1. Where do you start from to come to work?',
                            'moto_fueling' => 'What is your motorcycle or scooter\'s fueling type?',
                            'set_distance' => 'Set a distance greater than 0 and less than 100000.',
                            'next_continue' => 'Save and Continue',
                            'sel_type_auto' => 'Choose the type of car?',
                            'sel_type_bike' => 'Choose the type of bike?',
                            'sel_type_mono' => 'Choose the type of scooter?',
                            'sel_type_moto' => 'Choose the type of motorcycle?',
                            'type_Personal' => [
                                'Conducive' => 'Personal as driver',
                                'Passenger' => 'Personal as passenger'
                            ],
                            'Insert_address' => 'Insert an address, e.g. Via Roma 20, Settimo Torinese',
                            'amb_class_moto' => 'To which environmental class does your motorcycle belong?',
                            'insert_address' => 'Enter the address from which you leave most frequently, even if it is not the residence or domicile',
                            'sel_type_train' => 'Choose the type of train?',
                            'which_distance' => 'How much distance do you cover with this mode (round trip)?',
                            'meansOfTransport' => [
                                'Auto' => 'Car',
                                'Bike' => 'Bike',
                                'Moto' => 'Moto',
                                'Metro' => 'Metro',
                                'Train' => 'Train',
                                'On_foot' => 'on foot',
                                'Scooter' => 'Scooter',
                                'Bus/Tram' => 'Bus/Tram'
                            ],
                            'impossible_search' => 'Unable to find this address, try entering ',
                            'sel_type_Personal' => 'Choose if you are a driver or a passenger?',
                            'Mandatory_question' => 'Mandatory question',
                            'step_which_company' => 'In which office/company do you start work most frequently?',
                            'unavailable_address' => 'If the address is not recognized correctly, you can adjust the location by clicking on the map.'
                        ],
                        'it' => [
                            'do' => 'Fai click sul',
                            'Cap' => 'Cap',
                            'del' => 'Elimina questo mezzo',
                            'back' => 'Indietro',
                            'city' => 'Città',
                            'mean' => 'mezzo che usi uscendo da casa',
                            'next' => 'PROSSIMO',
                            'click' => 'Clicca qui per localizzarti sulla mappa',
                            'meters' => 'per almeno 500 metri',
                            'address' => 'Indirizzo',
                            'cc_auto' => 'Qual è la cilindrata della tua auto?',
                            'cc_moto' => 'Qual è la cilindrata della tua moto o ciclomotore?',
                            'province' => 'Provincia',
                            'research' => 'Ricerca in corso ...',
                            'set_cost' => 'Mettere un costo maggiore di 0 e minore di 100000. test',
                            'amb_class' => 'A quale classe ambientale appartiene la tua auto?',
                            'but_descr' => 'Usi un altro mezzo? Aggiungilo',
                            'type_auto' => [
                                'Taxi' => 'Taxi',
                                'Personal' => 'Personale',
                                'Car pooling' => 'Car pooling',
                                'Car sharing' => 'Car sharing',
                                'company_car' => 'Auto aziendale'
                            ],
                            'type_bike' => [
                                'Personal' => 'Personale',
                                'Bike sharing' => 'Bike sharing'
                            ],
                            'type_mono' => [
                                'Personal' => 'Personale',
                                'Scooter sharing' => 'Monopattino sharing'
                            ],
                            'type_moto' => [
                                'Personal' => 'Personale',
                                'Moto sharing' => 'Moto sharing'
                            ],
                            'insert_Cap' => 'Inserisci un Cap, es. 10036',
                            'mean_label' => 'Mezzo',
                            'type_train' => [
                                'Servizio ferroviario Trenitalia/GTT' => 'Servizio ferroviario Trenitalia/GTT',
                                'Servizio ferroviario altri vettori (Trenord, Italo, Alta Velocità, ...)' => 'Servizio ferroviario altri vettori (Trenord, Italo, Alta Velocità, ...)'
                            ],
                            'which_cost' => 'Quanto spendi al mese (A/R)?',
                            'car_fueling' => 'Qual è l\'alimentazione della tua auto?',
                            'insert_city' => 'Inserisci una città, es. Settimo Torinese',
                            'only_ci_pro' => 'solo città e provincia.',
                            'privacy_que' => 'Dubbi sulla privacy? Non stai fornendo un\'informazione riservata. L\'indirizzo è un dato di cui il tuo datore di lavoro dispone già. Verrà usato solo per questioni legate agli spostamenti casa-lavoro e non sarà comunicato a terzi se non in forma anonima.',
                            'select_auto' => 'Scegli il tipo di auto?',
                            'stepStrings' => [
                                'PRIMO',
                                'SECONDO',
                                'TERZO',
                                'QUARTO',
                                'QUINTO',
                                'SESTO',
                                'SETTIMO',
                                'OTTAVO',
                                'NONO',
                                'DECIMO'
                            ],
                            'which_modes' => 'Quali mezzi usi?',
                            'leavingpoint' => '1. Da dove parti per venire al lavoro?',
                            'moto_fueling' => 'Qual è l\'alimentazione della tua moto o ciclomotore?',
                            'set_distance' => 'Impostare una distanza maggiore di 0 e minore di 1000. test',
                            'next_continue' => 'Salva e Continua',
                            'sel_type_auto' => 'Scegli il tipo di auto?',
                            'sel_type_bike' => 'Scegli il tipo di Bici?',
                            'sel_type_mono' => 'Scegli il tipo di Monopattino?',
                            'sel_type_moto' => 'Scegli il tipo di moto?',
                            'type_Personal' => [
                                'Conducive' => 'Personale come conducente',
                                'Passenger' => 'Personale come passeggero'
                            ],
                            'Insert_address' => 'Inserisci un indirizzo, es. Via Roma 20, Settimo Torinese',
                            'amb_class_moto' => 'A quale classe ambientale appartiene la tua moto?',
                            'insert_address' => 'Inserisci l\'indirizzo da cui parti più di frequente, anche se non è la residenza o il domicilio',
                            'sel_type_train' => 'Scegli il tipo di Treno?',
                            'which_distance' => 'Quanta distanza percorri con questo mezzo (A/R) ?',
                            'meansOfTransport' => [
                                'Auto' => 'Auto',
                                'Bike' => 'Bici',
                                'Moto' => 'Moto',
                                'Metro' => 'Metro',
                                'Train' => 'Treno',
                                'On_foot' => 'Piedi',
                                'Scooter' => 'Monopattino',
                                'Bus/Tram' => 'Bus/Tram'
                            ],
                            'impossible_search' => 'Impossibile trovare questo indirizzo, prova ad inserire ',
                            'sel_type_personal' => 'Scegli se sei conducente oppure passaggero?',
                            'unavalible_adress' => 'Se l\'indirizzo non viene riconosciuto correttamente puoi aggiustare la posizione facendo click sulla mappa.',
                            'Mandatory_question' => 'Domanda obbligatoria',
                            'step_which_company' => 'In quale sede/azienda inizi il lavoro più di frequente?'
                        ],
                        "enabled" => [
                            "emissioni-auto" => true,
                            "emissioni-moto" => true,
                            "cilindrata-auto" => true,
                            "cilindrata-moto" => true,
                            "alimentazione-auto" => true,
                            "alimentazione-moto" => true
                        ],
            ]]),
                'options' => null,
                'conditions' => json_encode([
                    'value' => [[0], [0], [0], [0], [0], [0], [0]],
                    'groups' => [
                        'Costi dello spostamento',
                        'Tempi dello spostamento',
                        'Frequenza corse',
                        'Puntualità corse',
                        'Numero di interscambi',
                        'Comodità del viaggio',
                        'Sicurezza personale'
                    ],
                    'display' => 'conditionally',
                    'question' => 26,
                    'description' => 'Quanto ti ritieni soddisfatto dei seguenti aspetti riguardanti lo spostamento con i mezzi pubblici?'
                ]),
                'compulsory_answer' => 1
            ],
            [
                'survey_id' => 1,
                'question_id' => 203,
                'weight' => -90,
                'section_id' => 11,
                'compulsory' => 0,
                'hidden' => 1,
                'description' => 'Quale distanza percorri realmente per recarti al lavoro?  ',
                'long_description' => json_encode([
                    'description' => 'Calcola la distanza considerando tutti gli spostamenti che effettui (non solo la distanza diretta casa-lavoro)'
                ]),
                'options' => json_encode([
                    'fino a 1km;',
                    'fino a 3km',
                    'fino a 5km',
                    'da 5 a 15km; ',
                    'da 16 a 20km',
                    'da 21 a 30km',
                    'da 31 a 50km',
                    'oltre 50 km'
                ]),
                'conditions' => null,
                'compulsory_answer' => 1
            ],
            [
                'survey_id' => 1,
                'question_id' => 14,
                'weight' => -90,
                'section_id' => 11,
                'compulsory' => 0,
                'hidden' => 1,
                'description' => 'Qual è la cilindrata della tua auto?',
                'long_description' => json_encode([
                    'description' => ''
                ]),
                'options' => json_encode([
                    'Fino a 1200 cc',
                    'Fino a 1400 cc',
                    'Fino a 1600 cc',
                    'Fino a 1800 cc',
                    'Fino a 2000 cc',
                    'Oltre 2000 cc',
                    'Elettrico'
                ]),
                'conditions' => json_encode([
                    'value' => ['Benzina', 'Diesel', 'Ibrido', 'GPL', 'Metano'],
                    'groups' => [''],
                    'display' => 'conditionally',
                    'question' => 16,
                    'description' => 'Qual è l\'alimentazione della tua auto?'
                ]),
                'compulsory_answer' => 1
            ],
            [
                'survey_id' => 1,
                'question_id' => 15,
                'weight' => -90,
                'section_id' => 11,
                'compulsory' => 0,
                'hidden' => 1,
                'description' => 'Qual è la cilindrata della tua moto o ciclomotore?',
                'long_description' => json_encode([
                    'description' => ''
                ]),
                'options' => json_encode([
                    '50 cc',
                    '125 cc',
                    'Fino a 250 cc',
                    'Fino a 350 cc',
                    'Fino a 500 cc',
                    'Fino a 750 cc',
                    'Oltre 750 cc'
                ]),
                'conditions' => json_encode([
                    'value' => ['Benzina', 'A miscela'],
                    'groups' => [''],
                    'display' => 'conditionally',
                    'question' => 17,
                    'description' => 'Qual è l\'alimentazione della tua moto o ciclomotore?'
                ]),
                'compulsory_answer' => 1
            ],
            [
                'survey_id' => 1,
                'question_id' => 16,
                'weight' => -90,
                'section_id' => 11,
                'compulsory' => 0,
                'hidden' => 1,
                'description' => 'Qual è l\'alimentazione della tua auto?',
                'long_description' => json_encode([
                    'description' => ''
                ]),
                'options' => json_encode([
                    'Benzina',
                    'Diesel',
                    'Ibrido',
                    'Elettrico',
                    'GPL',
                    'Metano'
                ]),
                'conditions' => json_encode([
                    'value' => ['Sì'],
                    'groups' => [''],
                    'display' => 'conditionally',
                    'question' => 12,
                    'description' => 'Sei il conducente del mezzo che utilizzi per il tuo spostamento?'
                ]),
                'compulsory_answer' => 1
            ],
            [
                'survey_id' => 1,
                'question_id' => 17,
                'weight' => -90,
                'section_id' => 11,
                'compulsory' => 0,
                'hidden' => 1,
                'description' => 'Qual è l\'alimentazione della tua moto o ciclomotore?',
                'long_description' => json_encode([
                    'description' => ''
                ]),
                'options' => json_encode([
                    'Benzina',
                    'Elettrica',
                    'A miscela'
                ]),
                'conditions' => json_encode([
                    'value' => ['Moto/scooter'],
                    'groups' => [''],
                    'display' => 'conditionally',
                    'question' => 10,
                    'description' => 'Per lo spostamento di andata e ritorno quale mezzo utilizzi in prevalenza nei mesi invernali?'
                ]),
                'compulsory_answer' => 1
            ],
            [
                'survey_id' => 1,
                'question_id' => 341,
                'weight' => -90,
                'section_id' => 11,
                'compulsory' => 0,
                'hidden' => 1,
                'description' => 'Emissioni Auto',
                'long_description' => json_encode([
                    'description' => ''
                ]),
                'options' => json_encode([
                    'Euro 0',
                    'Euro 1',
                    'Euro 2',
                    'Euro 3',
                    'Euro 4',
                    'Euro 5',
                    'Euro 6'
                ]),
                'conditions' => null,
                'compulsory_answer' => 1
            ],
            [
                'survey_id' => 1,
                'question_id' => 342,
                'weight' => -90,
                'section_id' => 11,
                'compulsory' => 0,
                'hidden' => 1,
                'description' => 'Emissioni Moto',
                'long_description' => json_encode([
                    'description' => ''
                ]),
                'options' => json_encode([
                    'Euro 0',
                    'Euro 1',
                    'Euro 2',
                    'Euro 3',
                    'Euro 4',
                    'Euro 5',
                    'Euro 6'
                ]),
                'conditions' => null,
                'compulsory_answer' => 1
            ],
            [
                'survey_id' => 1,
                'question_id' => 180,
                'weight' => -90,
                'section_id' => 11,
                'compulsory' => 0,
                'hidden' => 1,
                'description' => 'Quanto spendi al mese per compiere lo spostamento?',
                'long_description' => json_encode([
                    'description' => 'Considera i costi di carburante, pedaggi,  parcheggi'
                ]),
                'options' => json_encode([
                    'Meno di 30 Euro',
                    'Tra 30 e 50 Euro',
                    'Tra 50 e 80 Euro',
                    'Più di 80 Euro',
                    'I costi per sostenere lo spostamento casa lavoro mi vengono completamente rimborsati',
                    'Utilizzo solo la tessera di servizio'
                ]),
                'conditions' => null,
                'compulsory_answer' => 1
            ],
            [
                'survey_id' => 1,
                'question_id' => 474,
                'weight' => -90,
                'section_id' => 11,
                'compulsory' => 0,
                'hidden' => 1,
                'description' => 'Come ti rechi più frequentemente al lavoro?',
                'long_description' => json_encode([
                    'description' => ''
                ]),
                'options' => json_encode([
                    'Auto'
                ]),
                'conditions' => null,
                'compulsory_answer' => 1
            ],
            [
                'survey_id' => 1,
                'question_id' => 3,
                'weight' => -90,
                'section_id' => 11,
                'compulsory' => 0,
                'hidden' => 1,
                'description' => 'Quanti chilometri percorri in auto/moto per raggiungere il tuo lavoro/scuola (per il viaggio di sola andata)?',
                'long_description' => json_encode([
                    'description' => 'Si chiede di specificare esattamente il numero di km percorsi con il mezzo privato, escludendo le tratte percorse con altri mezzi, ai fini del corretto calcolo degli impatti ambientali.'
                ]),
                'options' => json_encode([
                    '<2 km',
                    '2,1-5 km',
                    '5,1-10 km',
                    '10,1-15 km',
                    '15,1-25 km',
                    '25,1-50 km',
                    '50,1-75 km',
                    '75,1-100 km',
                    '100,1-125 km',
                    '125,1-150 km',
                    '>150 km',
                    'Dati non validi'
                ]),
                'conditions' => json_encode([
                    'value' => ['Auto', 'Moto/scooter'],
                    'groups' => [''],
                    'display' => 'conditionally',
                    'question' => 10,
                    'description' => 'Per lo spostamento di andata e ritorno quale mezzo utilizzi in prevalenza nei mesi invernali?'
                ]),
                'compulsory_answer' => 1
            ],
            [
                'survey_id' => 1,
                'question_id' => 166,
                'weight' => -90,
                'section_id' => 11,
                'compulsory' => 0,
                'hidden' => 1,
                'description' => 'Quanti mezzi di trasporto utilizzi per recarti al lavoro?',
                'long_description' => json_encode([
                    'description' => ''
                ]),
                'options' => json_encode([
                    'Uno solo',
                    'Combinazione di due o piu mezzi'
                ]),
                'conditions' => null,
                'compulsory_answer' => 0
            ],
            [
                'survey_id' => 1,
                'question_id' => 10,
                'weight' => -90,
                'section_id' => 11,
                'compulsory' => 0,
                'hidden' => 1,
                'description' => 'Per lo spostamento di andata e ritorno quale mezzo utilizzi in prevalenza nei mesi invernali?',
                'long_description' => json_encode([
                    'description' => 'Considera il mezzo prevalente quello che utilizzi per percorrere il maggior numero di chilometri'
                ]),
                'options' => json_encode([
                    'Piedi',
                    'Bus/Tram',
                    'Metro',
                    'Auto',
                    'Moto',
                    'Bici',
                    'Monopattino',
                    'Taxi',
                    'Treno',
                    'Servizio ferroviario altri vettori (Trenord, Italo, Alta Velocità...)',
                    'Car sharing',
                    'Bike sharing',
                    'Moto sharing',
                    'Monopattino sharing',
                    'Navetta aziendale',
                    'Car pooling'
                ]),
                'conditions' => json_encode([]),
                'compulsory_answer' => 0
            ],
            [
                'survey_id' => 1,
                'question_id' => 486,
                'weight' => -90,
                'section_id' => 11,
                'compulsory' => 0,
                'hidden' => 1,
                'description' => 'In quale sede stai lavorando prevalentemente?',
                'long_description' => json_encode([
                    'description' => ''
                ]),
                'options' => json_encode([
                    'Sede 2',
                    'Sede Principale'
                ]),
                'conditions' => null,
                'compulsory_answer' => 1
            ],
            [
                'survey_id' => 1,
                'question_id' => 497,
                'weight' => -90,
                'section_id' => 11,
                'compulsory' => 0,
                'hidden' => 1,
                'description' => 'Scegli il tipo di auto',
                'long_description' => json_encode([
                    'description' => ''
                ]),
                'options' => json_encode([
                    'Taxi',
                    'Personale',
                    'Car pooling',
                    'Car sharing'
                ]),
                'conditions' => null,
                'compulsory_answer' => 1
            ],
            [
                'survey_id' => 1,
                'question_id' => 498,
                'weight' => -90,
                'section_id' => 11,
                'compulsory' => 0,
                'hidden' => 1,
                'description' => 'Scegli se sei conducente oppure passeggero',
                'long_description' => json_encode([
                    'description' => ''
                ]),
                'options' => json_encode([
                    'Personale come conducente',
                    'Personale come passeggero'
                ]),
                'conditions' => null,
                'compulsory_answer' => 0
            ],
            [
                'survey_id' => 1,
                'question_id' => 499,
                'weight' => -90,
                'section_id' => 11,
                'compulsory' => 0,
                'hidden' => 1,
                'description' => 'Scegli il tipo di bici',
                'long_description' => json_encode([
                    'description' => ''
                ]),
                'options' => json_encode([
                    'Personale',
                    'Bike sharing'
                ]),
                'conditions' => null,
                'compulsory_answer' => 0
            ],
            [
                'survey_id' => 1,
                'question_id' => 500,
                'weight' => -90,
                'section_id' => 11,
                'compulsory' => 0,
                'hidden' => 1,
                'description' => 'Scegli il tipo di moto',
                'long_description' => json_encode([
                    'description' => ''
                ]),
                'options' => json_encode([
                    'Personale',
                    'Moto o scooter sharing'
                ]),
                'conditions' => null,
                'compulsory_answer' => 0
            ],
            [
                'survey_id' => 1,
                'question_id' => 501,
                'weight' => -90,
                'section_id' => 11,
                'compulsory' => 0,
                'hidden' => 1,
                'description' => 'Scegli il tipo di treno',
                'long_description' => json_encode([
                    'description' => ''
                ]),
                'options' => json_encode([
                    'Servizio ferroviario Trenitalia',
                    'Servizio ferroviario altri vettori (Trenord, Italo, Alta Velocità...)'
                ]),
                'conditions' => null,
                'compulsory_answer' => 1
            ],
            [
                'survey_id' => 1,
                'question_id' => 502,
                'weight' => -90,
                'section_id' => 11,
                'compulsory' => 0,
                'hidden' => 1,
                'description' => 'Globale - Scegli il tipo di monopattino',
                'long_description' => json_encode([
                    'description' => ''
                ]),
                'options' => json_encode([
                    'Personale',
                    'Scooter sharing'
                ]),
                'conditions' => null,
                'compulsory_answer' => 0
            ],
            [
                'survey_id' => 1,
                'question_id' => 8,
                'weight' => -40,
                'section_id' => 11,
                'compulsory' => 0,
                'hidden' => 0,
                'description' => 'Solitamente a che ora esci da lavoro il venerdì?',
                'long_description' => json_encode([
                    'description' => ''
                ]),
                'options' => json_encode([
                    'prima delle 14:00',
                    'dalle 14:00 alle 14:30',
                    'dalle 14:30 alle 15:00',
                    'dalle 15:30 alle 16:00',
                    'dalle 16:00 alle 16:30',
                    'dalle 16:30 alle 17:00',
                    'dalle 17:00 alle 17:30',
                    'dalle 17:30 alle 18:00',
                    'dalle 18:00 alle 18:30',
                    'dalle 18:30 alle 19:00',
                    'oltre le 19:00'
                ]),
                'conditions' => json_encode([
                    'value' => [0, 1],
                    'groups' => [''],
                    'display' => 'conditionally',
                    'question' => 501,
                    'description' => 'Scegli il tipo di treno'
                ]),
                'compulsory_answer' => 0
            ],
            [
                'survey_id' => 1,
                'question_id' => 346,
                'weight' => -85,
                'section_id' => 1,
                'compulsory' => 0,
                'hidden' => 0,
                'description' => 'Scegli il tipo di mezzo',
                'long_description' => json_encode([
                    'description' => ''
                ]),
                'options' => json_encode([
                    'bus urbano',
                    'bus extraurbano',
                    'tram',
                    'navetta aziendale'
                ]),
                'conditions' => null,
                'compulsory_answer' => 1
            ]
        ];

        $table = $this->table('questions_surveys');
        // empty the table
        $table->truncate();
        $table->insert($data)->save();
        
        // Insert mappings for survey_id = 2 using question IDs from emma_brescia.survey_id = 35
        // Copy fields from current DB 'questions' table (mirrors QuestionsSeed contents)
        $questionIds = [
            3,10,14,15,16,17,166,180,203,340,341,342,346,474,486,497,498,499,500,501,502,
            561,562,563,564,565,566,567,568,569,570,571,572,573,574,575,576,577,578,579,
            580,581,582,583,584,585,586,587,588,589,590,591,592,593,594,595,596,597,598,
            599,600,601,618
        ];

        if (!empty($questionIds)) {
            $idList = implode(',', array_map('intval', $questionIds));
            $sql = "SELECT id, description, options, conditions, long_description, compulsory_answer, section_id FROM questions WHERE id IN ($idList)";
            $rows = $this->fetchAll($sql);

            $insertRows = [];
            foreach ($rows as $row) {
                $insertRows[] = [
                    'survey_id' => 2,
                    'question_id' => (int)$row['id'],
                    'weight' => 0,
                    'section_id' => isset($row['section_id']) ? (int)$row['section_id'] : null,
                    'compulsory' => 0,
                    'hidden' => 0,
                    'description' => $row['description'] ?? null,
                    'long_description' => $row['long_description'] ?? null,
                    'options' => $row['options'] ?? null,
                    'conditions' => $row['conditions'] ?? null,
                    'compulsory_answer' => isset($row['compulsory_answer']) ? (int)$row['compulsory_answer'] : 0,
                ];
            }

            if (!empty($insertRows)) {
                $this->table('questions_surveys')->insert($insertRows)->save();
            }
        }
        
    }
}
