<?php

declare(strict_types=1);

namespace App\Http\Action;

use App\Application\AuthService;
use App\Http\Json;
use App\Http\SessionCookie;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** POST /api/auth/register — creates the account and logs it in: 201 with the user and a session cookie. */
final readonly class RegisterAction
{
    public function __construct(private AuthService $auth) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $session = $this->auth->register(Json::body($request));

        return SessionCookie::issue(Json::respond($response, ['data' => $session->user->toArray()], 201), $request, $session->token);
    }
}
