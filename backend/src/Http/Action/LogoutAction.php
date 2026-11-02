<?php

declare(strict_types=1);

namespace App\Http\Action;

use App\Application\AuthService;
use App\Http\SessionCookie;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** POST /api/auth/logout — ends the session on the server and clears the cookie. 204 even when already logged out. */
final readonly class LogoutAction
{
    public function __construct(private AuthService $auth) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->auth->logout(SessionCookie::read($request));

        return SessionCookie::clear($response->withStatus(204), $request);
    }
}
