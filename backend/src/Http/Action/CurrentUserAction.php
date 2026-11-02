<?php

declare(strict_types=1);

namespace App\Http\Action;

use App\Http\Json;
use App\Http\Middleware\RequireUser;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** GET /api/auth/me — the logged-in user, or 401. The SPA asks this on start. */
final readonly class CurrentUserAction
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Json::respond($response, ['data' => RequireUser::user($request)->toArray()]);
    }
}
