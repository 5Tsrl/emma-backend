<?php
use App\Indicator\emissionA;
use App\Indicator\emissionB;

return [
  'debug' => filter_var(env('DEBUG', false), FILTER_VALIDATE_BOOLEAN),
  'ExtraRoutes' => false,
  'DebugKit' => [
        'ignoreAuthorization' => true,
    ],
  'App.Languages' => ['it'],
  'specialTemplate' => [],        //indicare i tipi di contenuto statico per cui è necessario un template diverso
  //List the plugins you want to load in EMMA
  'extraplugins' => [    
    'Orariscuole',                // https://github.com/impronta48/emma-orariscuole
    'Reports',                    // https://github.com/impronta48/cake-sql-reports
  ],
  
  //'sitedir' => 'Moma',
  'sitedir' => '5T',
  'logodir' => 'companies/logo',
  'theme' => null,
  'copertina-pattern' => '/:sitedir/:model/:destination/:id/:field/',
  'default-image' => 'cartina-siti-locali.png',
  'VUE_APP_ICON' => env('VUE_APP_ICON', false),
  'VUE_APP_TITLE' => env('VUE_APP_TITLE', false),
  'VUE_APP_TITLE_LONG' => env('VUE_APP_TITLE_LONG', false),
  'VUE_APP_FOOTER' => env('VUE_APP_FOOTER', false),
  'VUE_APP_FOOTERALT' => env('VUE_APP_FOOTERALT', false),
  'VUE_APP_BACKGROUND' => env('VUE_APP_BACKGROUND', false),
  'VUE_APP_HELP_MAIL' => env('VUE_APP_HELP_MAIL', false),
  'abbonamenti' => false,

  //Nome del geocoding engine che si vuole utilizzare per geocodificare gli indirizzi
  //Viene chiamata una delle librerie contenute in /src/Geocoder
  //'Geocoding.Engine' => 'komootGeocoder',
  //'Geocoding.Url' => 'https://photon.komoot.io/api/',
  //'Geocoding.Engine' => 'nominatimGeocoder',
  //'Geocoding.Url' => 'https://nominatim.geocoding.ai/search',
  // 'Geocoding.Url' => env('GEOCODING_URL', false),
  //'Geocoding.Engine' => env('GEOCODING_ENGINE', false),
  //'Geocoding.Throttle' => 100,  //Numero di richieste al secondo ammesse
  //'Geocoding.Engine' => 'bunetGeocoder',
  //'Geocoding.Url' => 'https://www.bunet.torino.it/autocomplete',
  //'Geocoding.Engine' => 'peliasGeocoder',
  //'Geocoding.Url' => 'https://geocode.muoversinpiemonte.it',
  'Geocoding.Throttle' => 100,  //Numero di richieste al secondo ammesse
  'Geocoding.Engine' => env('GEOCODING_ENGINE', false),
  'Geocoding.accessKey' => env('GEOCODING_ACCESSKEY', false),
  'Geocoding.secretKey' => env('GEOCODING_SECRETKEY', false),
  'Geocoding.region' => env('GEOCODING_REGION', false),
  'Geocoding.placeIndexName' => env('GEOCODING_PLACEINDEXNAME', false),

  //Elenco di id di domande del questionario che devono comparire nei filtri della mappa
  //Utilizzato da QuestionsController
  'Origins.filter_question_id' => [2, 10],

  //Individua quali id di domande corrispondono alle origini della tabella origin
  //Utile per geocodificare le origin
  'Origins.origin_question_ids_map' => [
    43 => [
      'field' => 'city',
      'formatter' => 'json_array'
    ],
    44 => [
      'field' => 'address',
      'formatter' => 'self',
      'maxlen' => 99,
    ],
    206 => [
      'field' => 'postal_code',
      'formatter' => 'self',
      'maxlen' => 9,
    ]
  ],

  'api-whitelist' => explode(',', env('API_WHITELIST')),
  'Subscription.statusLegend' => [
    0 => 'ricevuta',
    1 => 'verificata',
    2 => 'inviata a tpl',
    3 => 'in attesa tpl',
    4 => 'pronto per ritiro',
    5 => 'ritirato',
  ],
  'OAuth.providers.auth0.options' => [
    'customDomain' => env('AUTH0_CUSTOMDOMAIN', false),
    'region'       => env('AUTH0_REGION', false),
    'clientId'     => env('AUTH0_CLIENTID', false),
    'clientSecret' => env('AUTH0_CLIENTSECRET', false)
  ],
  'Auth0' => [
    'endPoint' => env('AUTH0_ENDPOINT', false),
    'issuer' => env('AUTH0_ISSUER', false),
    'audience' => env('AUTH0_AUDIENCE', false),
    'userIdField' => env('AUTH0_USERIDFIELD', false),
  ],
  
  'Quickchart' => env('QUICKCHART', false),
  'FrontendUrl' => env('FRONTEND_URL', false),
  'MailAdmin' => [env('EMAIL_FROM', false) => env('EMAIL_DESCRIPTION', false)],
  'MailAgenzia' => [env('EMAIL_FROM', false) => env('EMAIL_DESCRIPTION', false)],
  'MailLogo' => env('MAILLOGO', false),
  
  'Datasources' => [
    'default' => [
      'className' => 'Cake\Database\Connection',
      'driver' => 'Cake\Database\Driver\Mysql',
      'persistent' => false,
      'host' => env('HOST', false),
      /*
             * CakePHP will use the default DB port based on the driver selected
             * MySQL on MAMP uses port 8889, MAMP users will want to uncomment
             * the following line and set the port accordingly
             */
      // 'port' => '3306',
      'username' => env('MYSQL_USER', false),
      'password' => env('MYSQL_PASSWORD', false),
      'database' => env('MYSQL_DATABASE', false),

      /*
             * You do not need to set this flag to use full utf-8 encoding (internal default since CakePHP 3.6).
             */
      //'encoding' => 'utf8mb4',
      'timezone' => 'UTC',
      'flags' => [],
      'cacheMetadata' => true,
      'log' => false,

      /**
       * Set identifier quoting to true if you are using reserved words or
       * special characters in your table or column names. Enabling this
       * setting will result in queries built using the Query Builder having
       * identifiers quoted when creating SQL. It should be noted that this
       * decreases performance because each query needs to be traversed and
       * manipulated before being executed.
       */
      'quoteIdentifiers' => false,

      /**
       * During development, if using MySQL < 5.6, uncommenting the
       * following line could boost the speed at which schema metadata is
       * fetched from the database. It can also be set directly with the
       * mysql configuration directive 'innodb_stats_on_metadata = 0'
       * which is the recommended value in production environments
       */
      //'init' => ['SET GLOBAL innodb_stats_on_metadata = 0'],

      'url' => env('DATABASE_URL', null),
    ],
    /*
         * The test connection is used during the test suite.
         */
    'test' => [
      'className' => 'Cake\Database\Connection',
      'driver' => 'Cake\Database\Driver\Mysql',
      'persistent' => false,
      'host' => env('HOST', false),
      //'encoding' => 'utf8mb4',
      'flags' => [],
      'username' => env('MYSQL_USER', false),
      'password' => env('MYSQL_PASSWORD', false),
      'database' => env('MYSQL_DATABASE', false),
      'cacheMetadata' => true,
      'quoteIdentifiers' => false,
      'log' => false,
      //'init' => ['SET GLOBAL innodb_stats_on_metadata = 0'],
    ],
  ],

  /*
     * Email configuration.
     *
     * Host and credential configuration in case you are using SmtpTransport
     *
     * See app.php for more configuration options.
     */
    // dev configuration should be set in .env
  // 'EmailTransport' => [
  //   'default' => [ 'className' => 'Mail',
  //   ],
    // prod configuration should be set in .env
    'EmailTransport' => [
      'default' => [
        'host' => env('SMTP_HOST', null),
        'port' => env('SMTP_PORT', null),
        'username' => env('SMTP_USERNAME', null),
        'password' => env('SMTP_PASSWORD', null),
        'className' => env('SMTP_CLASSNAME', 'Mail'),
        'tls' => env('SMTP_TLS', null),
      ],
  ],

  /*
     * Email delivery profiles
     *
     * Delivery profiles allow you to predefine various properties about email
     * messages from your application and give the settings a name. This saves
     * duplication across your application and makes maintenance and development
     * easier. Each profile accepts a number of keys. See `Cake\Mailer\Email`
     * for more information.
     */
  'Email' => [
    'default' => [
      'transport' => 'default',
      'from' => env('EMAIL_FROM', false),
      'to' => env('EMAIL_FROM', false),
      'host' => 'localhost',
      'port' => 1025,
    ],
  ],

  //Id delle domande fisse usate negli indicatori
  'Questions' => [
    'sesso' => 40,
    'eta' => 41,
    'sede' => 39,
    'inquadramento' => 186,
    'orario' => 188, //non sono sicura se il codice è quello giusto
    'giorni_sede' => 194,
    'smartworking' => 193,
    'flessibilita' => 191,
    'ora_partenza' => 5,
    'ora_uscita' => 7,
    'mezzo' => 10,
    'motivo_tpl' => 168,
    'motivo_privato' => 11,
    'contributo' => 183,
    'ripartizione_modale' => 10,
    'km_percorsi' => 3, //la 3 -> Quanti chilometri percorri in auto/moto?
    'durata_spostamento' => 195,
    'carburante' => 16,
    'per_modo_TPL' => 175,
    'per_modo_privato' => 18,
    //'gradimento' => 29,
    'disposto_tpl' => 20,
    'disposto_bici' => 177,
    'disposto_sharing' => 30, // per il momento considera solo carpooling, bisogna aggiungere sharing mobility in generale, es. 33, 176, 178
    //'disposto_carsharing' => 33,
    //'disposto_carpooling' => 30,
    'disposto_agile' => 169,
    'covide-mezzi' => 191,
    'origine_spostamenti' => 340,
  ],

  /** 2022-07-07 - Sebastian:  Questo gruppo di configurazioni riguarda la domanda di tipo mappa che salva nel db le sottodomande 
   * utile a mantenere la compatibilità con anaylitics e tutto il sistema esisente
   */
  'Questions_spos' => [
    'quale_distanza' => 203,
    //'anno_auto' => 13,
    'cilindrata_auto' => 14,
    'cilindrata_moto' => 15,
    'alimentazione_auto' => 16,
    'alimentazione_moto' => 17,
    'emissioni_auto' => 341,
    'emissioni_moto' => 342,
    'costo_spostamento' => 180,
    'mezzi' => 10,
    'mezzo' => 474,
    'quale_distanza_auto' => 3,
    'nr_mezzi' => 166,
    'sede_mappa' => 486,
    'tipo_auto' => 497,
    'personale_auto' => 498,
    'tipo_bici' => 499,
    'tipo_moto' => 500,
    'tipo_treno' => 501,
    'tipo_monopattino' => 502
  ],

  //I colori usati dai grafici di word
  'chartColors' => [
    '3366cc', 'dc3912', 'ff9900', '109618', '990099', '0099c6', 'dd4477',
    '66aa00', 'b82e2e', '316395', '994499', '22aa99', 'aaaa11', '6633cc', 'e67300', '8b0707',
    '651067', '329262', '5574a6', '3b3eac', 'b77322', '16d620', 'b91383', 'f4359e', '9c5935',
    'a9c413', '2a778d', '668d1c', 'bea413', '0c5922', '743411'
  ],

  /** 2023-04-18 - Sebastian:  Questo gruppo di configurazioni riguarda la domanda di tipo mappa che salva nel db le sottodomande 
   * utile a mantenere la compatibilità con anaylitics e tutto il sistema esisente
   */
  'Coworking' => [
    'types' => [1=>"Sala riunioni",2=>"Ufficio dedicato",3=>"Postazione di lavoro in ufficio condiviso"],
  ],
  'Exporter' => [
    'extensions' => ['md', 'html', 'docx', 'pdf','nextcloud'],   //supported extensions 
  ],
    
  //Configurazioni aggiunte per il frontend
  'WhitelistConfigurations' => ['chartColors','frontendPermissions','Questions_spos','Questions'],

  //Massimoi - 5/1/23
  //NON ANCORA UTILIZZATO! PER ORA VARIABILI DI CONFIGURAZIONE .ENV
  //Questi sono i permessi/funzionalità che voglio abilitare per il frontend
  //Li mettiamo tutti e poi commentiamo quelli che non vogliamo passare per quella specifica istanza
  'frontendPermissions' => [    
      //'md-generate',
      'mobility-label',
      'anon-only',
      'user-guide',
      'show-impacts-xls',
      'translate',
      // 'coworking',
      'livelli-informativi',
  ]
];