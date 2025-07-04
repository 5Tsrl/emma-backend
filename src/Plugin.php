<?php
declare(strict_types=1);

namespace App;

use Cake\Core\BasePlugin;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\MiddlewareQueue;

class Plugin extends BasePlugin
{
    public function routes($routes): void
    {
        // Add routes.
        // By default will load `config/routes.php` in the plugin.
        parent::routes($routes);
    }

    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        // Bind the error handler into the middleware queue.
        $middlewareQueue->add(new ErrorHandlerMiddleware());
        // only JSON will be parsed.
        $middlewareQueue->add(new BodyParserMiddleware());

        return $middlewareQueue;
    }
}
