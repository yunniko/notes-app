<?php

declare(strict_types=1);

namespace App\Http\Action;

use App\Application\AuthService;
use App\Http\Json;
use App\Http\SessionCookie;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** POST /api/auth/login — 200 with the user and a new session cookie; 401 or 429 otherwise. */
final readonly class LoginAction
{
    public function __construct(private AuthService $auth) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $session = $this->auth->login(Json::body($request));

        return SessionCookie::issue(Json::respond($response, ['data' => $session->user->toArray()]), $request, $session->token);
    }
}
