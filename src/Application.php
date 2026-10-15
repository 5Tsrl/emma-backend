<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     3.3.0
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */

namespace App;

//Authentication

use App\Authenticator\CookieJwtAuthenticator;
use App\Identifier\MultiProfileAuth0Identifier;
use App\Identifier\MultiProfilePasswordIdentifier;
use App\Identifier\Resolver\EncryptedOrmResolver;
use App\Middleware\HostValidationMiddleware;
use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Authentication\AuthenticationServiceProviderInterface;
use Authentication\Identifier\IdentifierInterface;
use Authentication\Middleware\AuthenticationMiddleware;
// Authorization
use Authorization\AuthorizationService;
use Authorization\AuthorizationServiceInterface;
use Authorization\AuthorizationServiceProviderInterface;
use Authorization\Middleware\AuthorizationMiddleware;
use Authorization\Policy\OrmResolver;
use Cake\Core\Configure;
use Cake\Core\Exception\MissingPluginException;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\BaseApplication;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\Middleware\CspMiddleware;
use Cake\Http\Middleware\SecurityHeadersMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\I18n\Middleware\LocaleSelectorMiddleware;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Application setup class.
 *
 * This defines the bootstrapping logic and middleware layers you
 * want to use in your application.
 */
class Application extends BaseApplication implements
    AuthenticationServiceProviderInterface,
    AuthorizationServiceProviderInterface
{
    /**
     * Load all the application configuration and bootstrap logic.
     *
     * @return void
     */
    public function bootstrap(): void
    {
      // Call parent to load bootstrap from files.
        parent::bootstrap();

        if (PHP_SAPI === 'cli') {
            $this->bootstrapCli();
        }

      /*
             * Only try to load DebugKit in development mode
             * Debug Kit should not be installed on a production system
             */
        if (Configure::read('debug')) {
            $this->addPlugin('DebugKit');
        }

      // Load more plugins here
        $this->addPlugin('Authentication');
        $this->addPlugin('Notifications');      //https://github.com/impronta48/angelcake-notifications
        $this->addPlugin('EmailQueue');
        $this->addPlugin('Authorization');
        $this->addPlugin('Crud');
        $this->addPlugin('SecretField');

      //Questi plugin vengono caricati da una variabile di configurazione
        if (Configure::check('extraplugins')) {
            $plugins = Configure::read('extraplugins');
            if (!empty($plugins)) {
                foreach ($plugins as $p) {
                    $this->addPlugin($p, ['routes' => true, 'bootstrap' => true]);
                }
            }
        }
    }

    /**
     * Setup the middleware queue your application will use.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to setup.
     * @return \Cake\Http\MiddlewareQueue The updated middleware queue.
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $middlewareQueue
        // Add the new HostValidationMiddleware to the beginning of the queue
        ->add(new HostValidationMiddleware())

        // Catch any exceptions in the lower layers,
        // and make an error page/response
        ->add(new ErrorHandlerMiddleware(Configure::read('Error')))

        // Add the clickjacking protection headers.
        ->add((new SecurityHeadersMiddleware())->setXFrameOptions('sameorigin'))
        ->add((new CspMiddleware(['frame-ancestors' => ["'self'"]])))

        // Handle plugin/theme assets like CakePHP normally does.
        ->add(new AssetMiddleware([
        'cacheTime' => Configure::read('Asset.cacheTime'),
        ]))

        // Add routing middleware.
        // If you have a large number of routes connected, turning on routes
        // caching in production could improve performance. For that when
        // creating the middleware instance specify the cache config name by
        // using it's second constructor argument:
        // `new RoutingMiddleware($this, '_cake_routes_')`
        ->add(new RoutingMiddleware($this, '_cake_routes_'))

        // Parse various types of encoded request bodies so that they are
        // available as array through $request->getData()
        // https://book.cakephp.org/4/en/controllers/middleware.html#body-parser-middleware
        ->add(new BodyParserMiddleware())

        // Negotiate the response language from the Accept-Language header
        // (the SPA sends its active vue-i18n locale). Sets I18n::setLocale()
        // so __() and the Translate behavior return the right language.
        // Restricted to supported locales; unknown values fall back to
        // App.defaultLocale ('it'). German ('de') is survey-only.
        ->add(new LocaleSelectorMiddleware(['it', 'fr', 'de']))

        ->add(new AuthenticationMiddleware($this))
        ->add(new AuthorizationMiddleware($this));
      //   ->add(new AuthorizationMiddleware($this, [
      //     'identityDecorator' => function ($auth, $user) {
      //         return $user->setAuthorization($auth);
      //     }
      // ]));
      // Cross Site Request Forgery (CSRF) Protection Middleware
      // https://book.cakephp.org/4/en/controllers/middleware.html#cross-site-request-forgery-csrf-middleware
      // Currently disabled project-wide (never added to the queue below), so
      // there is nothing to exempt today. Kept ready, not just noted, for
      // whoever re-enables it: POST /api/spid/acs (plugins/Spid) is a
      // legitimate foreign-origin POST - the SPID Identity Provider's
      // browser submitting the SAML Response - and can never carry this
      // app's CSRF token. See plugins/Spid/README.md.
      // Path shown as it appears under this app's public base (APP_BASE, e.g.
      // "/api" per config/sites/<host>/.env) - verify the exact value
      // $request->getUri()->getPath() carries at this middleware layer
      // before uncommenting, since that depends on how far upstream (nginx)
      // strips the base path.
      /*  ->add(new CsrfProtectionMiddleware([
                    'httponly' => true,
                    'skipCheckCallback' => function ($request) {
                        return $request->getUri()->getPath() === '/api/spid/acs';
                    },
                ])); */

        return $middlewareQueue;
    }

    /**
     * Bootstrapping for CLI application.
     *
     * That is when running commands.
     *
     * @return void
     */
    protected function bootstrapCli(): void
    {
        try {
            $this->addPlugin('Bake');
        } catch (MissingPluginException $e) {
          // Do not halt if the plugin is missing
        }

        try {
            $this->addPlugin('IdeHelper');
        } catch (MissingPluginException $e) {
          // require-dev only (dereuromark/cakephp-ide-helper) - absent on a
          // production `composer install --no-dev`. Dev tool only, CLI-only:
          // generates IDE/LSP annotation stubs, never loaded for web requests.
        }

        $this->addPlugin('Migrations');
        $this->addPlugin('Authentication');
        $this->addPlugin('EmailQueue');
        $this->addPlugin('SecretField');

      // Load more plugins here
    }

    /**
     * Returns a service provider instance.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return \Authentication\AuthenticationServiceInterface
     */
    public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
    {
        $service = new AuthenticationService();

      //Leggo dalla configurazione i metodi di login abilitati
        $loginMethods = Configure::read('LoginMethods');

      // Define where users should be redirected to when they are not authenticated
        $service->setConfig([
        'unauthenticatedRedirect' => null, //'/users/login',
        'queryParam' => 'redirect',
        ]);

        $fields = [
        IdentifierInterface::CREDENTIAL_USERNAME => 'email',
        IdentifierInterface::CREDENTIAL_PASSWORD => 'password',
        ];

        $resolver = [
        'className' => EncryptedOrmResolver::class,
        'userModel' => 'Users',
        'finder' => 'forAuthentication',
        ];

      // Load the authenticators. Session should be first.
        $service->loadAuthenticator('Authentication.Session', [
      // 'resolver' => $resolver,
        ]);
        $service->loadAuthenticator(CookieJwtAuthenticator::class, [
        //'secretKey' => Security::getSalt(), //file_get_contents(CONFIG . '/jwt.pem'),
        'algorithms' => ['HS256'],
        'returnPayload' => false,
        'cookie' => 'jwt_token',     // legge dal cookie
        'logAttempts' => true,
        // 'resolver' => $resolver,
        ]);

        $service->loadIdentifier('Authentication.JwtSubject', [
        'resolver' => $resolver,
        ]);

      // Se più record Users corrispondono alle stesse credenziali/allo stesso claim
      // IdP (es. lo stesso dipendente con un account su più aziende), gli identifier
      // "multi-profile-aware" propongono la scelta al client invece di autenticare
      // arbitrariamente il primo risultato — vedi MultiProfilePasswordIdentifier/
      // MultiProfileAuth0Identifier e UsersController::login()/selectProfile().
      // Disattivabile con MULTI_PROFILE_LOGIN=false (fallback agli identifier
      // standard del plugin Authentication).
        $multiProfileLogin = Configure::read('MultiProfileLogin.enabled');

      //Accetto anche login via auth0
        if (in_array('auth0', $loginMethods)) {
            $service->loadAuthenticator('Auth0', [
            'jwksEndpoint' =>  Configure::read('Auth0.endPoint'),
            'issuer' =>  Configure::read('Auth0.issuer'),
            'audience' =>  Configure::read('Auth0.audience'),
            'algorithms' => ['RS256', 'HS256'],
            'returnPayload' => false,
            'subjectKey' => Configure::read('Auth0.userIdField'),
            // 'resolver' => $resolver,
            ]);

            $auth0IdentifierClass = $multiProfileLogin ? MultiProfileAuth0Identifier::class : 'Auth0';
            $service->loadIdentifier($auth0IdentifierClass, [
            'tokenField' => 'email',
            'dataField' => Configure::read('Auth0.userIdField'),
            'resolver' => $resolver,
            ]);
        }

      //Accetto anche login via form
        if (in_array('form', $loginMethods)) {
            $service->loadAuthenticator('Authentication.Form', [
            'fields' => $fields,
            'loginUrl' => null,
            // 'resolver' => $resolver,
            ]);

            $passwordIdentifierClass = $multiProfileLogin
                ? MultiProfilePasswordIdentifier::class
                : 'Authentication.Password';
            $service->loadIdentifier($passwordIdentifierClass, compact('fields', 'resolver'));
        }

        return $service;
    }

    public function getAuthorizationService(ServerRequestInterface $request): AuthorizationServiceInterface
    {
        $resolver = new OrmResolver();

        return new AuthorizationService($resolver);
    }
}
