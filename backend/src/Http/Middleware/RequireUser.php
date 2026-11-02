<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\AuthService;
use App\Domain\Exception\AuthenticationRequired;
use App\Domain\User;
use App\Http\SessionCookie;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Lets a request through only with a valid session, and attaches its user to the request. */
final readonly class RequireUser implements MiddlewareInterface
{
    private const string ATTRIBUTE = 'user';

    public function __construct(private AuthService $auth) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $this->auth->userForToken(SessionCookie::read($request)) ?? throw new AuthenticationRequired();

        return $handler->handle($request->withAttribute(self::ATTRIBUTE, $user));
    }

    /**
     * The logged-in user. Throws rather than guessing when a route was wired without this
     * middleware, so a missing check fails closed.
     */
    public static function user(ServerRequestInterface $request): User
    {
        $user = $request->getAttribute(self::ATTRIBUTE);

        return $user instanceof User ? $user : throw new AuthenticationRequired();
    }
}
