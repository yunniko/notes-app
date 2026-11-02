<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;

/**
 * Refuses state-changing requests that a browser sent on behalf of another site (CSRF, D011).
 * SameSite=Lax already keeps the cookie off cross-site POSTs; this also covers sibling
 * subdomains, which count as the same site. Browsers report the relation in Sec-Fetch-Site;
 * older ones only send Origin, which is then compared with Host. Requests with neither
 * header do not come from a browser page and carry no ambient cookie risk.
 */
final readonly class SameOriginGuard implements MiddlewareInterface
{
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!in_array($request->getMethod(), self::SAFE_METHODS, true) && !$this->isSameOrigin($request)) {
            throw new HttpForbiddenException($request, 'Requests from other sites are not accepted.');
        }

        return $handler->handle($request);
    }

    private function isSameOrigin(ServerRequestInterface $request): bool
    {
        $site = $request->getHeaderLine('Sec-Fetch-Site');
        if ($site !== '') {
            return $site === 'same-origin' || $site === 'none';
        }

        $origin = $request->getHeaderLine('Origin');
        if ($origin === '') {
            return true;
        }
        $host = parse_url($origin, PHP_URL_HOST);
        $port = parse_url($origin, PHP_URL_PORT);
        if (!is_string($host)) {
            return false;
        }

        return strcasecmp($host . (is_int($port) ? ':' . $port : ''), $request->getHeaderLine('Host')) === 0;
    }
}
