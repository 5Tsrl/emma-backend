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
use Cake\Http\MiddlewareQueue;
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

      //Questi plugin vengono caricati da una variabile di configurazione
        if (Configure::check('extraplugins')) {
            $plugins = Configure::read('extraplugins');
            if (!empty($plugins)) {
                foreach ($plugins as $p) {
                    $this->addPlugin($p);
                }
            }
        }
        // $this->addPlugin('NextcloudStorage');
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
        // Catch any exceptions in the lower layers,
        // and make an error page/response
        ->add(new ErrorHandlerMiddleware(Configure::read('Error')))

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
        ->add(new RoutingMiddleware($this))

        // Parse various types of encoded request bodies so that they are
        // available as array through $request->getData()
        // https://book.cakephp.org/4/en/controllers/middleware.html#body-parser-middleware
        ->add(new BodyParserMiddleware())

        ->add(new AuthenticationMiddleware($this))
        ->add(new AuthorizationMiddleware($this));
      //   ->add(new AuthorizationMiddleware($this, [
      //     'identityDecorator' => function ($auth, $user) {
      //         return $user->setAuthorization($auth);
      //     }
      // ]));
      // Cross Site Request Forgery (CSRF) Protection Middleware
      // https://book.cakephp.org/4/en/controllers/middleware.html#cross-site-request-forgery-csrf-middleware
      /*  ->add(new CsrfProtectionMiddleware([
                    'httponly' => true,
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

        $this->addPlugin('Migrations');
        $this->addPlugin('Authentication');
        $this->addPlugin('EmailQueue');

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
          'className' => 'Authentication.Orm',
          'userModel' => 'Users',
          'finder' => 'forAuthentication',
          ];

      // Load the authenticators. Session should be first.
        $service->loadAuthenticator('Authentication.Session', [
          // 'resolver' => $resolver,
        ]);
        $service->loadAuthenticator('Authentication.Jwt', [
      //'secretKey' => Security::getSalt(), //file_get_contents(CONFIG . '/jwt.pem'),
        'algorithms' => ['HS256'],
        'returnPayload' => false,
        // 'resolver' => $resolver,
        ]);
        $service->loadAuthenticator('Auth0', [
        'jwksEndpoint' =>  Configure::read('Auth0.endPoint'),
        'issuer' =>  Configure::read('Auth0.issuer'),
        'audience' =>  Configure::read('Auth0.audience'),
        'algorithms' => ['RS256', 'HS256'],
        'returnPayload' => false,
        'subjectKey' => Configure::read('Auth0.userIdField'),
        // 'resolver' => $resolver,
        ]);

        $service->loadAuthenticator('Authentication.Form', [
        'fields' => $fields,
        'loginUrl' => null,
        // 'resolver' => $resolver,
        ]);

      // Load identifiers
        $service->loadIdentifier('Authentication.Password', compact('fields', 'resolver'));
        $service->loadIdentifier('Authentication.JwtSubject', [
          'resolver' => $resolver,
        ]);
        $service->loadIdentifier('Auth0', [
        'tokenField' => 'email',
        'dataField' => Configure::read('Auth0.userIdField'),
        'resolver' => $resolver,
        ]);

        return $service;
    }

    public function getAuthorizationService(ServerRequestInterface $request): AuthorizationServiceInterface
    {
        $resolver = new OrmResolver();

        return new AuthorizationService($resolver);
    }
}
