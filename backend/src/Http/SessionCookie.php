<?php

declare(strict_types=1);

namespace App\Http;

use App\Application\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The session cookie (D011): HttpOnly so scripts cannot read it, SameSite=Lax so other sites
 * cannot send it with their POSTs, Secure whenever the request arrived over HTTPS, and scoped
 * to /api because nothing else needs it.
 */
final class SessionCookie
{
    public const string NAME = 'notes_session';

    /** The token from the request, or null when absent or not shaped like one of ours. */
    public static function read(ServerRequestInterface $request): ?string
    {
        $token = $request->getCookieParams()[self::NAME] ?? self::fromHeader($request->getHeaderLine('Cookie'));

        return is_string($token) && preg_match('/^[0-9a-f]{64}$/D', $token) === 1 ? $token : null;
    }

    public static function issue(ResponseInterface $response, ServerRequestInterface $request, #[\SensitiveParameter] string $token): ResponseInterface
    {
        return $response->withAddedHeader('Set-Cookie', self::header($request, $token, AuthService::SESSION_LIFETIME_SECONDS));
    }

    public static function clear(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface
    {
        return $response->withAddedHeader('Set-Cookie', self::header($request, '', 0));
    }

    private static function header(ServerRequestInterface $request, string $value, int $maxAge): string
    {
        // TLS ends at the reverse proxy, which reports the original scheme in X-Forwarded-Proto.
        // A client faking it only makes its own cookie unusable over plain HTTP.
        $https = $request->getUri()->getScheme() === 'https'
            || strtolower($request->getHeaderLine('X-Forwarded-Proto')) === 'https';

        return sprintf(
            '%s=%s; Path=/api; Max-Age=%d; HttpOnly; SameSite=Lax%s',
            self::NAME,
            $value,
            $maxAge,
            $https ? '; Secure' : '',
        );
    }

    private static function fromHeader(string $header): ?string
    {
        foreach (explode(';', $header) as $pair) {
            [$name, $value] = array_pad(explode('=', trim($pair), 2), 2, '');
            if ($name === self::NAME) {
                return $value;
            }
        }

        return null;
    }
}
