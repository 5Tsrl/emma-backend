<?php
use App\Indicator\emissionA;
use App\Indicator\emissionB;

//<editor-fold desc="Preamble">
/**
 * EMMA(tm) : Electronic Mobility Management Applications
 * Copyright (c) 5T Torino, Regione Piemonte, Città Metropolitana di Torino
 *
 * SPDX-License-Identifier: EUPL-1.2
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) 5T - https://5t.torino.it
 * @link      https://emma.5t.torino.it
 * @author    Massimo INFUNTI - https://github.com/impronta48
 * @license   https://eupl.eu/1.2/it/ EUPL-1.2 license
 */
//</editor-fold>

return [
  'debug' => true,
  'sitedir' => 'Emma',            //create a folder in Webroot where attachments will be stored
  'logodir' => 'companies/logo',  //folder under sitedir where company logos will be stored
  //List the plugins you want to load in EMMA
  'extraplugins' => [    
    'Orariscuole',                // https://github.com/5Tsrl/emma-backend-orariscuole.git
    'Reports',                    // https://github.com/impronta48/cake-sql-reports
  ],

  //Configurations concerning the frontend  
  'VUE_APP_ICON' => "emma-logo.png",
  'VUE_APP_TITLE' => "EMMA-5T",
  'VUE_APP_TITLE_LONG' => "EMMA - Piattaforma Mobility Management 5T",
  'VUE_APP_FOOTER' => "5T-logo.jpg",
  'VUE_APP_FOOTERALT' => "5T, Regione Piemonte, Città Metropolitana di Torino, Città di Torino",
  'VUE_APP_BACKGROUND' => "whitesmoke",
  'VUE_APP_HELP_MAIL' => "mobilita.sostenibile@5t.torino.it",

  //Name of the geocoding engine that will be used to geocode in the platform
  //This is the name of a class found in /src/Geocoder
  'Geocoding.Throttle' => 100,  //Numer of requests per second
  'Geocoding.Engine' => 'peliasGeocoder',
  'Geocoding.Url' => 'https://geocode.muoversinpiemonte.it',

  //Alternative geocoders
  //'Geocoding.Engine' => 'komootGeocoder',
  //'Geocoding.Url' => 'https://photon.komoot.io/api/',
  //'Geocoding.Engine' => 'nominatimGeocoder',
  //'Geocoding.Engine' => 'bunetGeocoder',
  //'Geocoding.Url' => 'https://www.bunet.torino.it/autocomplete',

  //Id of the questions that we want in the filters of the map
  //Used by QuestionsController
  'Origins.filter_question_id' => [2, 10],

  //Id of the questions in the survey that correspond to the origin
  //Used for geocoding origins
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

  //URL accepted by the backend (url of the frontend)
  'FrontendUrl' => 'https://5t.drupalvm.test:8080',
  'api-whitelist' => [
    'https://5t.drupalvm.test:8080',
    'http://5t.drupalvm.test:8080',
    'https://localhost:8080',
    'http://localhost:8080',
  ],

  //Auth0 provider, to be customized with the parameters of your provider
  'OAuth.providers.auth0.options' => [
    'customDomain' => '',
    'region'       => '',
    'clientId'     => '',               //Check on Auth0 web site
    'clientSecret' => '',               //Check on Auth0 web site
  ],
  'Auth0' => [
    'endPoint' => '',
    'issuer' => '',
    'audience' => '',
    'userIdField' => '',
  ],
  
  //Quickchart server used by the PSCL generation
  'Quickchart' => 'chart.5t.torino.it',
  //'Quickchart' => 'quickchart.io',

  //Email address used for notifications and logo to be used
  'MailAdmin' => ['mobilita.sostenibile@5t.torino.it' => 'Mobilità Sostenibile 5T'],
  'MailLogo' => "/img/emma-logo.png",

  //Database configuration (see https://book.cakephp.org/4/en/orm/database-basics.html#database-configuration)  
  'Datasources' => [
    'default' => [
      'className' => 'Cake\Database\Connection',
      'driver' => 'Cake\Database\Driver\Mysql',
      'persistent' => false,
      'host' => env('dbhost','127.0.0.1'),
      //'port' => 'non_standard_port_number',
      'username' => '',                   //Insert here your db user
      'password' => '',                   //Insert here your db password
      'database' => '',                   //Insert here your db name
      //'encoding' => 'utf8mb4',          //Backward compatibility
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
      'host' => 'localhost',
      //'encoding' => 'utf8mb4',
      'flags' => [],
      'username' => '',                   //Insert here your db user
      'password' => '',                   //Insert here your db password
      'database' => '',                   //Insert here your db name
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
  //https://book.cakephp.org/4/en/core-libraries/email.html#email-configuration
  'EmailTransport' => [
    'Debug' => [
      'className' => 'Debug',
    ],
    'default' => [
      'className' => 'Mail',
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
      'from' => 'mobilita.sostenibile@5t.torino.it',
    ],
  ],

  //Questions used by the map question to store derivate values (compatible with the previous system)
  'Questions_spos' => [
    'quale_distanza' => 203,
    //'anno_auto' => 13,
    'cilindrata_auto' => 14,
    'cilindrata_moto' => 15,
    'alimentazione_auto' => 16,
    'alimentazione_moto' => 17,
    'emissioni_auto' => 339,
    'emissioni_moto' => 342,
    'costo_spostamento' => 180,
    'mezzo' => 10,
    'quale_distanza_auto' => 3,
    'nr_mezzi' => 166,
  ],

  //Colors userd by the word charts (not used anymore)
  'chartColors' => [
    '3366cc', 'dc3912', 'ff9900', '109618', '990099', '0099c6', 'dd4477',
    '66aa00', 'b82e2e', '316395', '994499', '22aa99', 'aaaa11', '6633cc', 'e67300', '8b0707',
    '651067', '329262', '5574a6', '3b3eac', 'b77322', '16d620', 'b91383', 'f4359e', '9c5935',
    'a9c413', '2a778d', '668d1c', 'bea413', '0c5922', '743411'
  ],

  //Plugin - Orari Scuole
  //Email address used to send to the Local Transport Agency (plugin orari scuole)  
  'MailAgenzia' => ['mobilita.sostenibile@5t.torino.it' => 'Mobilità Sostenibile 5T'],

  //Extra Features - Ignore
  'abbonamenti' => false,
  'Subscription.statusLegend' => [
    0 => 'ricevuta',
    1 => 'verificata',
    2 => 'inviata a tpl',
    3 => 'in attesa tpl',
    4 => 'pronto per ritiro',
    5 => 'ritirato',
  ],
  
  //Keep this for compatibility with the angelcakeplatform, do not use for EMMA
  'theme' => '',                  
  'copertina-pattern' => '/:sitedir/:model/:destination/:id/:field/',
  'specialTemplate' => [],
  'ExtraRoutes' => false,
  'App.Languages' => ['it'],
  'default-image' => 'cartina-siti-locali.png',
];
