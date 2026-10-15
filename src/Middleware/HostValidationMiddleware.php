<?php
declare(strict_types=1);

namespace App\Middleware;

use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Log\Log;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Validates the host header against a safelist to prevent SSRF attacks.
 */
class HostValidationMiddleware implements MiddlewareInterface
{
    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param \Psr\Http\Server\RequestHandlerInterface $handler The next handler.
     * @return \Psr\Http\Message\ResponseInterface A response.
     * @throws \Cake\Http\Exception\ForbiddenException when the host is not in the safelist.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $approvedHosts = Configure::read('Security.approvedHosts', []);
        $host = $request->getUri()->getHost();

        // Also check X-Forwarded-Host if it exists
        $xForwardedHost = $request->getHeaderLine('X-Forwarded-Host');
        if ($xForwardedHost && !in_array($xForwardedHost, $approvedHosts, true)) {
            Log::warning(sprintf('Blocked request from unapproved X-Forwarded-Host: "%s"', $xForwardedHost));
            throw new ForbiddenException('Invalid host header.');
        }

        if (!in_array($host, $approvedHosts, true)) {
            Log::warning(sprintf('Blocked request from unapproved host: "%s"', $host));
            throw new ForbiddenException('Invalid host header.');
        }

        return $handler->handle($request);
    }
}
