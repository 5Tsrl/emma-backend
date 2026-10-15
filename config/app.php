<?php

use Cake\Cache\Engine\FileEngine;
use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Database\Driver\Mysql;
use Cake\Error\ExceptionRenderer;
use Cake\Log\Engine\FileLog;

return [
  /*
     * Debug Level:
     *
     * Production Mode:
   * false: No error messages, errors, or warnings shown.
   *
   * Development Mode:
   * true: Errors and warnings shown.
   */
  'debug' => filter_var(env('DEBUG', false), FILTER_VALIDATE_BOOLEAN),
  'DebugKit' => [
    'ignoreAuthorization' => true,
  ],

  /*
   * Configure basic information about the application.
     *
     * - namespace - The namespace to find app classes under.
     * - defaultLocale - The default locale for translation, formatting currencies and numbers, date and time.
     * - encoding - The encoding used for HTML + database connections.
     * - base - The base directory the app resides in. If false this
     *   will be auto detected.
     * - dir - Name of app directory.
     * - webroot - The webroot directory.
     * - wwwRoot - The file path to webroot.
     * - baseUrl - To configure CakePHP to *not* use mod_rewrite and to
     *   use CakePHP pretty URLs, remove these .htaccess
     *   files:
     *      /.htaccess
     *      /webroot/.htaccess
     *   And uncomment the baseUrl key below.
     * - fullBaseUrl - A base URL to use for absolute links. When set to false (default)
     *   CakePHP generates required value based on `HTTP_HOST` environment variable.
     *   However, you can define it manually to optimize performance or if you
     *   are concerned about people manipulating the `Host` header.
     * - imageBaseUrl - Web path to the public images directory under webroot.
     * - cssBaseUrl - Web path to the public css directory under webroot.
     * - jsBaseUrl - Web path to the public js directory under webroot.
     * - paths - Configure paths for non class based resources. Supports the
     *   `plugins`, `templates`, `locales` subkeys, which allow the definition of
     *   paths for plugins, view templates and locale files respectively.
     */
  'App' => [
    'namespace' => 'App',
    'encoding' => env('APP_ENCODING', 'UTF-8'),
    'defaultLocale' => env('APP_DEFAULT_LOCALE', 'it'),
    'defaultTimezone' => env('APP_DEFAULT_TIMEZONE', 'Europe/Rome'),
    //Bisogna mettere false per mantenere compatibilità con CakePHP <4
    'uploadedFilesAsObjects' => false,
    'base' => env('APP_BASE', false),
    'webroot' => 'webroot',
    // 'fullBaseUrl' => false,
    'fullBaseUrl' => env('APP_FULL_BASE_URL', false),
    'imageBaseUrl' => 'img/',
    'dir' => 'src',
    'wwwRoot' => WWW_ROOT,
    'cssBaseUrl' => 'css/',
    'jsBaseUrl' => 'js/',
    'paths' => [
      'plugins' => [ROOT . DS . 'plugins' . DS],
      'templates' => [ROOT . DS . 'templates' . DS],
      'locales' => [RESOURCES . 'locales' . DS],
    ],
  ],

  /*
     * Security and encryption configuration
     *
     * - salt - A random string used in security hashing methods.
     *   The salt value is also used as the encryption key.
     *   You should treat it as extremely sensitive data.
     */
  'Security' => [
    'salt' => env('SECURITY_SALT'),
    /**
     * Approved hostnames.
     *
     * This is a list of hostnames that your application is allowed to respond to.
     * Requests with a host header that is not in this list will be blocked.
     * This helps prevent SSRF and host header injection attacks.
     *
     * Example:
     * 'approvedHosts' => [
     *     'example.com',
     *     'www.example.com',
     * ],
     */
    'approvedHosts' => explode(',', env('APPROVED_HOSTS', 'localhost,127.0.0.1')),
  ],

  'SecretField' => [
    // 32-byte key, strongly recommended as base64 string prefixed by "base64:"
    'key' => env('SECRET_FIELD_KEY', ''),
  ],

  /*
     * Apply timestamps with the last modified time to static assets (js, css, images).
     * Will append a querystring parameter containing the time the file was modified.
     * This is useful for busting browser caches.
     *
     * Set to true to apply timestamps when debug is true. Set to 'force' to always
     * enable timestamping regardless of debug value.
     */
  'Asset' => [
    //'timestamp' => true,
    // 'cacheTime' => '+1 year'
    'timestamp' => 'false',
  ],

  /*
     * Configure the cache adapters.
     */
  'Cache' => [
    'default' => [
      'className' => FileEngine::class,
      'path' => CACHE,
      'url' => Configure::read('sitedir'),
      'duration' => '+5 minutes',
    ],

    'origins' => [
      'className' => FileEngine::class,
      'path' => CACHE,
      'url' => env('CACHE_DEFAULT_URL', null),
      'groups' => ['origins'],
      'duration' => '+5 minutes',
    ],

    'long' => [
      'className' => FileEngine::class,
      'probability' => 100,
      'path' => CACHE . 'long' . DS,
      'duration' => '+1 week',
    ],

    'hour' => [
      'className' => FileEngine::class,
      'probability' => 100,
      'path' => CACHE . 'hour' . DS,
      'duration' => '+1 hour',
    ],

    'dashboard' => [
      'className' => FileEngine::class,
      'probability' => 100,
      'path' => CACHE . 'dashboard' . DS,
      'groups' => ['dashboard'],
      'serialize' => true,
      'duration' => '+5 minutes',
    ],

    'employee_stats' => [
      'className' => FileEngine::class,
      'probability' => 100,
      'path' => CACHE . 'employee_stats' . DS,
      'groups' => ['employee_stats'],
      'serialize' => true,
      'duration' => '+30 minutes',
    ],

    'offices' => [
      'className' => FileEngine::class,
      'probability' => 100,
      'path' => CACHE . 'offices' . DS,
      'groups' => ['offices'],
      'serialize' => true,
      'duration' => '+30 minutes',
    ],

    /*
         * Configure the cache used for general framework caching.
         * Translation cache files are stored with this configuration.
         * Duration will be set to '+2 minutes' in bootstrap.php when debug = true
         * If you set 'className' => 'Null' core cache will be disabled.
         */
    '_cake_core_' => [
      'className' => FileEngine::class,
      'prefix' => 'myapp_cake_core_',
      'path' =>  CACHE . Configure::read('sitedir') . 'persistent' . DS,
      'serialize' => true,
      'duration' => '+1 years',
      'url' => env('CACHE_CAKECORE_URL', null),
    ],

    /*
         * Configure the cache for model and datasource caches. This cache
         * configuration is used to store schema descriptions, and table listings
         * in connections.
         * Duration will be set to '+2 minutes' in bootstrap.php when debug = true
         */
    '_cake_model_' => [
      'className' => FileEngine::class,
      'prefix' => 'myapp_cake_model_',
      'path' =>  CACHE . Configure::read('sitedir') . 'models' . DS,
      'serialize' => true,
      'duration' => '+1 years',
      'url' => env('CACHE_CAKEMODEL_URL', null),
    ],

    /*
         * Configure the cache for routes. The cached routes collection is built the
         * first time the routes are processed through `config/routes.php`.
         * Duration will be set to '+2 seconds' in bootstrap.php when debug = true
         */
    '_cake_routes_' => [
      'className' => FileEngine::class,
      'prefix' => 'myapp_cake_routes_',
      'path' =>  CACHE . Configure::read('sitedir') . 'routes' . DS,
      'serialize' => true,
      'duration' => '+1 years',
      'url' => env('CACHE_CAKEROUTES_URL', null),
    ],

    'img' => [
      'className' => 'File',
      'prefix' => 'img_',
      'path' =>  CACHE . Configure::read('sitedir') . 'gallery' . DS,
      'serialize' => true,
      'duration' => '+1 years',
      'url' => env('CACHE_CAKEMODEL_URL', null),
    ],
  ],

  /*
     * Configure the Error and Exception handlers used by your application.
     *
     * By default errors are displayed using Debugger, when debug is true and logged
     * by Cake\Log\Log when debug is false.
     *
     * In CLI environments exceptions will be printed to stderr with a backtrace.
     * In web environments an HTML page will be displayed for the exception.
     * With debug true, framework errors like Missing Controller will be displayed.
     * When debug is false, framework errors will be coerced into generic HTTP errors.
     *
     * Options:
     *
     * - `errorLevel` - int - The level of errors you are interested in capturing.
     * - `trace` - boolean - Whether or not backtraces should be included in
     *   logged errors/exceptions.
     * - `log` - boolean - Whether or not you want exceptions logged.
     * - `exceptionRenderer` - string - The class responsible for rendering
     *   uncaught exceptions. If you choose a custom class you should place
     *   the file for that class in src/Error. This class needs to implement a
     *   render method.
     * - `skipLog` - array - List of exceptions to skip for logging. Exceptions that
     *   extend one of the listed exceptions will also be skipped for logging.
     *   E.g.:
     *   `'skipLog' => ['Cake\Http\Exception\NotFoundException', 'Cake\Http\Exception\UnauthorizedException']`
     * - `extraFatalErrorMemory` - int - The number of megabytes to increase
     *   the memory limit by when a fatal error is encountered. This allows
     *   breathing room to complete logging or error handling.
     */
  'Error' => [
    'errorLevel' => E_ALL & ~E_USER_DEPRECATED,
    'exceptionRenderer' => ExceptionRenderer::class,
    'skipLog' => [],
    'log' => true,
    'trace' => true,
  ],

  /**
   * Debugger configuration
   *
   * Define development error values for Cake\Error\Debugger
   *
   * - `editor` Set the editor URL format you want to use.
   *   By default atom, emacs, macvim, phpstorm, sublime, textmate, and vscode are
   *   available. You can add additional editor link formats using
   *   `Debugger::addEditor()` during your application bootstrap.
   * - `outputMask` A mapping of `key` to `replacement` values that
   *   `Debugger` should replace in dumped data and logs generated by `Debugger`.
   */
  'Debugger' => [
    'editor' => 'phpstorm',
  ],

  /*
     * Email configuration.
     *
     * By defining transports separately from delivery profiles you can easily
     * re-use transport configuration across multiple profiles.
     *
     * You can specify multiple configurations for production, development and
     * testing.
     *
     * Each transport needs a `className`. Valid options are as follows:
     *
     *  Mail   - Send using PHP mail function
     *  Smtp   - Send using SMTP
     *  Debug  - Do not send the email, just return the result
     *
     * You can add custom transports (or override existing transports) by adding the
     * appropriate file to src/Mailer/Transport. Transports should be named
     * 'YourTransport.php', where 'Your' is the name of the transport.
     */
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

  /*
     * Connection information used by the ORM to connect
     * to your application's datastores.
     *
     * ### Notes
     * - Drivers include Mysql Postgres Sqlite Sqlserver
     *   See vendor\cakephp\cakephp\src\Database\Driver for complete list
     * - Do not use periods in database name - it may lead to error.
     *   See https://github.com/cakephp/cakephp/issues/6471 for details.
     * - 'encoding' is recommended to be set to full UTF-8 4-Byte support.
     *   E.g set it to 'utf8mb4' in MariaDB and MySQL and 'utf8' for any
     *   other RDBMS.
     */
  'Datasources' => [
    /**
     * These configurations should contain permanent settings used
     * by all environments.
     *
     * The values in app_local.php will override any values set here
     * and should be used for local and per-environment configurations.
     *
     * Environment variable based configurations can be loaded here or
     * in app_local.php depending on the applications needs.
     */
    'default' => [
      'className' => Connection::class,
      'driver' => Mysql::class,
      'persistent' => false,
      'timezone' => 'UTC',
      /**
       * For MariaDB/MySQL the internal default changed from utf8 to utf8mb4, aka full utf-8 support, in CakePHP 3.6
       */
      //'encoding' => 'utf8mb4',

      /**
       * If your MySQL server is configured with `skip-character-set-client-handshake`
       * then you MUST use the `flags` config to set your charset encoding.
       * For e.g. `'flags' => [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4']`
       */
      'flags' => [],
      'cacheMetadata' => true,
      'log' => false,

      /*
             * Set identifier quoting to true if you are using reserved words or
             * special characters in your table or column names. Enabling this
             * setting will result in queries built using the Query Builder having
             * identifiers quoted when creating SQL. It should be noted that this
             * decreases performance because each query needs to be traversed and
             * manipulated before being executed.
             *
             * Enabled (true) because `subscriptions_tariffe.key` (plugins/Subscriptions,
             * see TariffeTable) is a MySQL reserved word: with this off, any ORM-built
             * query touching that column (SELECT/INSERT/UPDATE via find()/save()) fails
             * with a MySQL syntax error near `key`. Toggling this per-table isn't viable
             * (the underlying Driver instance/auto-quoting flag is shared by every Table
             * on the same connection), so it's enabled connection-wide instead — safe for
             * every other table too, since quoting a non-reserved identifier with
             * backticks never changes query semantics.
             */
      'quoteIdentifiers' => true,

      /*
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
      'className' => Connection::class,
      'driver' => Mysql::class,
      'persistent' => false,
      'timezone' => 'UTC',
      //'encoding' => 'utf8mb4',
      'flags' => [],
      'cacheMetadata' => true,
      // Kept in sync with the `default` connection above (see the comment there) so
      // PHPUnit exercises the same quoting behavior as production.
      'quoteIdentifiers' => true,
      'log' => false,
      //'init' => ['SET GLOBAL innodb_stats_on_metadata = 0'],
      'url' => env('DATABASE_TEST_URL', null),
    ],
  ],

  /*
     * Configures logging options
     */
  'Log' => [
    'debug' => [
      'className' => FileLog::class,
      'path' => LOGS,
      'file' => 'debug',
      'url' => env('LOG_DEBUG_URL', null),
      'scopes' => false,
      'levels' => ['notice', 'info', 'debug'],
    ],
    'error' => [
      'className' => FileLog::class,
      'path' => LOGS,
      'file' => 'error',
      'url' => env('LOG_ERROR_URL', null),
      'scopes' => false,
      'levels' => ['warning', 'error', 'critical', 'alert', 'emergency'],
    ],
    // To enable this dedicated query log, you need set your datasource's log flag to true
    'queries' => [
      'className' => FileLog::class,
      'path' => LOGS,
      'file' => 'queries',
      'url' => env('LOG_QUERIES_URL', null),
      'scopes' => ['queriesLog'],
    ],
    'login' => [
      'className' => 'File',
      'path' => LOGS,
      'file' => 'login',
      'scopes' => ['login'],
      'levels' => ['info'],
    ],
    'permits' => [
      'className' => 'File',
      'path' => LOGS,
      'file' => 'permits',
      'scopes' => ['permits'],
      'levels' => ['info'],
    ],
  ],

  /*
     * Session configuration.
     *
     * Contains an array of settings to use for session configuration. The
     * `defaults` key is used to define a default preset to use for sessions, any
     * settings declared here will override the settings of the default config.
     *
     * ## Options
     *
     * - `cookie` - The name of the cookie to use. Defaults to value set for `session.name` php.ini config.
     *    Avoid using `.` in cookie names, as PHP will drop sessions from cookies with `.` in the name.
     * - `cookiePath` - The url path for which session cookie is set. Maps to the
     *   `session.cookie_path` php.ini config. Defaults to base path of app.
     * - `timeout` - The time in minutes the session should be valid for.
     *    Pass 0 to disable checking timeout.
     *    Please note that php.ini's session.gc_maxlifetime must be equal to or greater
     *    than the largest Session['timeout'] in all served websites for it to have the
     *    desired effect.
     * - `defaults` - The default configuration set to use as a basis for your session.
     *    There are four built-in options: php, cake, cache, database.
     * - `handler` - Can be used to enable a custom session handler. Expects an
     *    array with at least the `engine` key, being the name of the Session engine
     *    class to use for managing the session. CakePHP bundles the `CacheSession`
     *    and `DatabaseSession` engines.
     * - `ini` - An associative array of additional ini values to set.
     *
     * The built-in `defaults` options are:
     *
     * - 'php' - Uses settings defined in your php.ini.
     * - 'cake' - Saves session files in CakePHP's /tmp directory.
     * - 'database' - Uses CakePHP's database sessions.
     * - 'cache' - Use the Cache class to save sessions.
     *
     * To define a custom session handler, save it at src/Network/Session/<name>.php.
     * Make sure the class implements PHP's `SessionHandlerInterface` and set
     * Session.handler to <name>
     *
     * To use database sessions, load the SQL file located at config/schema/sessions.sql
     */
  'Session' => [
    'defaults' => 'php',
    'ini' => [
      'session.cookie_httponly' => 1,
    ],
  ],

  'ExtraRoutes' => false,
  'App.Languages' => ['it'],
  'specialTemplate' => [],        //indicare i tipi di contenuto statico per cui è necessario un template diverso
  //List the plugins you want to load
  'extraplugins' => env('EXTRA_PLUGINS', null) ? explode(',', env('EXTRA_PLUGINS')) : [],
  'sitedir' => env('SITE_DIR', null) ? env('SITE_DIR') . DS : '',
  'theme' => env('THEME', null),
  'copertina-pattern' => '/:sitedir/:model/:destination/:id/:field/',
  'default-image' => 'cartina-siti-locali.png',
  'VUE_APP_ICON' => env('VUE_APP_ICON', false),
  'VUE_APP_TITLE' => env('VUE_APP_TITLE', false),
  'VUE_APP_TITLE_LONG' => env('VUE_APP_TITLE_LONG', false),
  'VUE_APP_FOOTER' => env('VUE_APP_FOOTER', false),
  'VUE_APP_FOOTERALT' => env('VUE_APP_FOOTERALT', false),
  'VUE_APP_BACKGROUND' => env('VUE_APP_BACKGROUND', false),
  'VUE_APP_HELP_MAIL' => env('VUE_APP_HELP_MAIL', false),
  'VUE_APP_THEME_BRESCIA' => env('VUE_APP_THEME_BRESCIA', false),
  'VUE_APP_THEME_5T' => env('VUE_APP_THEME_5T', false),
  'Geocoding.Throttle' => 100,  //Numero di richieste al secondo ammesse
  'Geocoding.Engine' => env('GEOCODING_ENGINE', false),
  'Geocoding.accessKey' => env('GEOCODING_ACCESSKEY', false),
  'Geocoding.secretKey' => env('GEOCODING_SECRETKEY', false),
  'Geocoding.region' => env('GEOCODING_REGION', false),
  'Geocoding.placeIndexName' => env('GEOCODING_PLACEINDEXNAME', false),
  'Geocoding.url' => env('GEOCODING_URL', false),
  'Geocoding.google.accessKey' => env('GEOCODING_GOOGLE_ACCESSKEY', false),

  //Elenco di id di domande del questionario che devono comparire nei filtri della mappa
  //Utilizzato da QuestionsController
  'Origins.filter_question_id' => env('ORIGINS_FILTER_QUESTION_ID', null) ? explode(',', env('ORIGINS_FILTER_QUESTION_ID')) : [2, 10],

  //Individua quali id di domande corrispondono alle origini della tabella origin
  //Utile per geocodificare le origin
  'Origins.origin_question_ids_map' => json_decode(env('ORIGINS_MAP'), true),

  //Numero massimo di righe restituite da OriginsController::index() per ogni singola richiesta
  //(non si applica alle richieste count=1, che restituiscono sempre il totale reale).
  //Protegge da query non filtrate (es. company_id=0, "tutte le aziende") su tabelle molto grandi:
  //vedi CLAUDE.md "Origins index memory-safety cap".
  'Origins.maxResults' => (int)env('ORIGINS_MAX_RESULTS', 5000),
  'api-whitelist' => explode(',', env('API_WHITELIST', 'https://emma.5t.torino.it')),

  'OAuth.providers.auth0.options' => [
    'customDomain' => env('AUTH0_CUSTOMDOMAIN', false),
    'region'       => env('AUTH0_REGION', false),
    'clientId'     => env('AUTH0_CLIENTID', false),
    'clientSecret' => env('AUTH0_CLIENTSECRET', false),
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
  // Mittente forzato per le email di indagine (es. Brescia: emmabrescia@...). Vuoto = nessun forzamento.
  'Surveys' => ['forcedSenderEmail' => env('SURVEY_FORCED_SENDER_EMAIL', '')],
  'MailLogo' => env('MAILLOGO', false),
  'MailFooterInlineLogo' => filter_var(env('MAIL_FOOTER_INLINE_LOGO', 'false'), FILTER_VALIDATE_BOOLEAN),
  'MailPoweredBy' => filter_var(env('MAIL_POWERED_BY', 'true'), FILTER_VALIDATE_BOOLEAN),
  'Questions' => env('INDICATOR_QUESTIONS', null) ? json_decode(env('INDICATOR_QUESTIONS'), true) : [],

  /** 2022-07-07 - Sebastian:  Questo gruppo di configurazioni riguarda la domanda di tipo mappa che salva nel db le sottodomande
   * utile a mantenere la compatibilità con anaylitics e tutto il sistema esisente
   */
  'Questions_spos' => env('QUESTIONS_SPOS', null) ? json_decode(env('QUESTIONS_SPOS'), true) : [],

  /**
   * CompanyQuestionnaireTemplates
   * Mappa version_tag ('Azienda'|'Scuola'|'Uni') => scope ('generale'|'sede') => id
   * della survey "template" (version_tag='template') da cui viene clonato il
   * questionario dinamico di profilo azienda/scuola/università, reso omogeneo ai
   * questionari normali (stessa tabella `surveys`, stesso motore Questions/
   * QuestionsSurveys/Answers) al posto dei vecchi componenti Vue statici.
   * Vedi SurveysTable::findOrCreateCompanyQuestionnaire() e
   * docs/dev-notes/company-questionnaires-dynamic-conversion.md.
   */
  'CompanyQuestionnaireTemplates' => env('COMPANY_QUESTIONNAIRE_TEMPLATES', null)
    ? json_decode(env('COMPANY_QUESTIONNAIRE_TEMPLATES'), true)
    : [],

  //Elenco delle domande che richiedono una analisi di tipo range
  'Range_questions' =>
    env('RANGE_QUESTIONS', null) ?
    explode(',', env('RANGE_QUESTIONS')) :
    ['costo-spostamento', 'quale-distanza', 'quale-distanza-auto', 'spesa-spostamento', 'distanza-totale'],

  //I colori usati dai grafici di word
  'chartColors' => env('CHART_COLORS', null) ? explode(',', env('CHART_COLORS')) : [
    '3366cc',
    'dc3912',
    'ff9900',
    '109618',
    '990099',
    '0099c6',
    'dd4477',
    '66aa00',
    'b82e2e',
    '316395',
    '994499',
    '22aa99',
    'aaaa11',
    '6633cc',
    'e67300',
    '8b0707',
    '651067',
    '329262',
    '5574a6',
    '3b3eac',
    'b77322',
    '16d620',
    'b91383',
    'f4359e',
    '9c5935',
    'a9c413',
    '2a778d',
    '668d1c',
    'bea413',
    '0c5922',
    '743411',
  ],

  /** 2023-04-18 - Sebastian:  Questo gruppo di configurazioni riguarda la domanda di tipo mappa che salva nel db le sottodomande
   * utile a mantenere la compatibilità con anaylitics e tutto il sistema esisente
   */
  'Coworking' => [
    'types' => env('COWORKING_TYPES', null) ? json_decode(env('COWORKING_TYPES'), true) : [1 => 'Sala riunioni', 2 => 'Ufficio dedicato', 3 => 'Postazione di lavoro in ufficio condiviso'],
  ],
  'Exporter' => [
    'extensions' => env('EXPORTER_EXTENSIONS', null) ? explode(',', env('EXPORTER_EXTENSIONS')) : ['md', 'html', 'docx', 'pdf', 'nextcloud'],   //supported extensions
  ],
  /**
   * EmployeeImportFields
   * In questa sezione vengono definiti i campi da importare quando si usa la funzione
   * importa dipendenti dell'azienda (qualcuno la chiama matrice o/d dei dipendenti).
   * Questi campi devono essere presenti nell'XLS che si importa, nell'ordine corretto.
   */
  'EmployeeImportFields' => explode(',', env('EMPLOYEE_IMPORT_FIELDS', false)),

  //Elenco delle configurazioni che possono essere lette da frontend
  'WhitelistConfigurations' => env('WHITELIST_CONFIGURATIONS', null)
    ? explode(',', env('WHITELIST_CONFIGURATIONS'))
    : ['chartColors', 'frontendPermissions', 'Questions_spos', 'Questions', 'EmployeeImportFields', 'Coworking.types'],

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
  ],
  // Sebastian - 3/10/25
  // Aggiunta della configurazione per i distretti
  'Districts' => env('DISTRICTS', null) ? explode(',', env('DISTRICTS')) : [],

  'LoginMethods' => env('LOGIN_METHODS', null) ? explode(',', env('LOGIN_METHODS')) : ['form'],
  // Gestione password (reset/cambio/creazione). false per i siti i cui utenti
  // accedono solo tramite IdP esterno (es. RER via IAM/SPID).
  'PasswordManagement' => filter_var(env('PASSWORD_MANAGEMENT', 'true'), FILTER_VALIDATE_BOOLEAN),
  // Login con più profili corrispondenti (stesso username/email/CF su più record
  // Users, es. un dipendente con account su più aziende). Quando il login (form o
  // auth0/oauth) trova più di un record valido, il backend NON ne sceglie uno a
  // caso: propone la lista al frontend (modale "scegli profilo") e completa
  // l'accesso solo dopo la scelta esplicita, vedi UsersController::login()/
  // selectProfile(). Per i flussi che NON passano dal componente Authentication
  // (IdP esterni come IAM/SPID nel plugin Rer, che leggono header e interrogano
  // Users direttamente) il campo "identificante" non è deducibile dal codice
  // generico: va dichiarato qui per contesto.
  'MultiProfileLogin' => [
    'enabled' => filter_var(env('MULTI_PROFILE_LOGIN', 'true'), FILTER_VALIDATE_BOOLEAN),
    // Validità (secondi) del pending-login token restituito quando la scelta è
    // ambigua: il frontend lo rimanda a users/select-profile.json col profilo scelto.
    'pendingTtl' => (int)env('MULTI_PROFILE_LOGIN_TTL', 300),
    // Campo users.* da usare per cercare i duplicati nei flussi che non passano dal
    // componente Authentication (form/auth0 lo deducono già da soli dal proprio
    // identifier: username/email). Chiave = nome del flusso esterno, valore = colonna.
    'externalFields' => [
      'iam' => 'cf',
      'spid' => 'cf',
    ],
  ],
  // PSCL "5 assi invece di 10 pilastri" cutover year — OPT-IN per brand, mirroring the
  // PasswordManagement pattern above. 0/unset (the default) disables historicization
  // entirely: the brand always serves the current measures.php config for every
  // requested year. Only the 5t brand sets a real cutover year (2027) in its site .env;
  // years >= the cutover use the new flat type=0/weight structure, years < the cutover
  // load the historicized measures.php snapshot.
  'PsclCutoverYear' => (int)env('PSCL_CUTOVER_YEAR', 0),
  // Template PSCL di default (`pscl` row con version_tag='template') assegnato
  // automaticamente a `companies.pscl_template_id` quando una nuova azienda
  // viene creata senza che un admin ne indichi uno esplicitamente (vedi
  // CompaniesController::edit()). Usato poi da PsclController::edit() per
  // seminare automaticamente il piano (tutti i valori a 0, vedi
  // PsclTable::buildPlanFromTemplate()) quando la pagina Interventi crea un
  // nuovo PSCL per l'azienda senza che l'utente scelga esplicitamente un
  // template/anno da cui copiare. null (default) = nessun default a livello
  // di sito: il piano viene comunque costruito dal catalogo Measures corrente
  // (PsclTable::buildPlanFromMeasures()).
  'PsclDefaultTemplateId' => env('PSCL_DEFAULT_TEMPLATE_ID', null) !== null
    ? (int)env('PSCL_DEFAULT_TEMPLATE_ID', null)
    : null,
  // Modulo di adesione pubblico (form di adesione alla piattaforma). Attivo solo sui
  // brand che lo prevedono (es. RER); altrove il form pubblico risponde 404.
  'AdhesionEnabled' => filter_var(env('ADHESION_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN),
  // Requisito 3 della compliance privacy RER "EMMA RE-R": documento aziendale sul
  // trattamento dati dei dipendenti, upload (via link di adesione), lettura pubblica
  // e hard-gate sull'invio inviti survey. Stesso pattern opt-in di AdhesionEnabled
  // sopra: 'false' di default, 'true' nel .env del sito RER.
  'EmployeePrivacyDocGate' => filter_var(env('EMPLOYEE_PRIVACY_DOC_GATE', 'false'), FILTER_VALIDATE_BOOLEAN),
  // PDF privacy caricabile dal MOMA per azienda (Brescia). Mutuamente esclusivo con EmployeePrivacyDocGate
  // per upload/delete. Vedi docs/dev-notes/survey-privacy-pdf.md
  'SurveyPrivacyPdf' => filter_var(env('SURVEY_PRIVACY_PDF', 'false'), FILTER_VALIDATE_BOOLEAN),
  'filesPath' => env('FILES_PATH', null) ? env('FILES_PATH') . DS : 'private_uploads' . DS,
  // Protocollo elettronico
  'Protocollo' => [
    'baseEndpoint' => env('PROTOCOLLO_BASE_ENDPOINT', false),
    'userService' => env('PROTOCOLLO_USERNAME', false),
    'password' => env('PROTOCOLLO_PASSWORD', false),
    'NumeroPratica' => env('PROTOCOLLO_NUMERO_PRATICA', '6.5.1.5'),
  ],
  'Geohash' => [
    'precision' => env('GEOHASH_PRECISION', 7), // precision 5 corrisponde a circa 4.9km x 4.9km, precision 6 a circa 1.2km x 0.61km
  ],
  // SPID (Italian national SAML2 identity federation), plugins/Spid - Phase 1
  // is metadata-only (login()/acs()/pending() are 501 stubs). Real SAML2 to
  // a genuine IdP via italia/spid-php-lib, unlike RER's header-trusting
  // "spid" (plugins/Rer\IamController, no actual SAML). SPID_SP_KEY_FILE /
  // SPID_SP_CERT_FILE / SPID_IDP_METADATA_DIR are ROOT-relative paths (e.g.
  // "config/spid/sp/sp.key") resolved to absolute paths here - the files
  // themselves live under the gitignored config/spid/ (never commit them).
  // See plugins/Spid/README.md for the full env/endpoint contract and the
  // session-state decision (RelayState is signed & self-contained; SPID
  // never relies on $_SESSION, because the ACS POST is cross-site and this
  // app's session/JWT cookies are SameSite=Lax).
  'Spid' => [
    'entityId' => env('SPID_SP_ENTITY_ID', false),
    'spKeyFile' => env('SPID_SP_KEY_FILE', null) ? ROOT . DS . env('SPID_SP_KEY_FILE') : false,
    'spCertFile' => env('SPID_SP_CERT_FILE', null) ? ROOT . DS . env('SPID_SP_CERT_FILE') : false,
    'idpMetadataDir' => env('SPID_IDP_METADATA_DIR', null) ? ROOT . DS . env('SPID_IDP_METADATA_DIR') : false,
    'acsUrl' => env('SPID_ACS_URL', false),
    // Not one of the task's listed env vars, but required by the library
    // itself (sp_singlelogoutservice is a REQUIRED settings key) - see
    // plugins/Spid/src/SpidPlugin.php's routes() comment on the /slo stub.
    'sloUrl' => env('SPID_SLO_URL', false),
    'level' => (int)env('SPID_LEVEL', 2),
    'attributes' => env('SPID_ATTRIBUTES', null)
      ? explode(',', env('SPID_ATTRIBUTES'))
      : ['name', 'familyName', 'fiscalNumber', 'email'],
    'allowedIdps' => env('SPID_ALLOWED_IDPS', null) ? explode(',', env('SPID_ALLOWED_IDPS')) : ['demo'],
    // italia/spid-php-lib only emits the SP metadata's <md:Organization>
    // block when 'sp_org_name'/'sp_org_display_name' are present in its
    // settings array (Saml.php ~line 143: `array_key_exists('sp_org_name', ...)`)
    // - without it, a compliant validator (e.g. Demo SPID's "Upload ZIP"
    // metadata check) rejects the metadata outright. See SpidSettings::toArray().
    'orgName' => env('SPID_SP_ORG_NAME', false),
    'orgDisplayName' => env('SPID_SP_ORG_DISPLAY_NAME', false),
  ],
];
