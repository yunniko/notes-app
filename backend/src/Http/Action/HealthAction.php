<?php

declare(strict_types=1);

namespace App\Http\Action;

use App\Http\Json;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** GET /api/health — 200 when the API can reach the database; used by the Docker healthcheck. */
final readonly class HealthAction
{
    public function __construct(private PDO $pdo) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->pdo->query('SELECT 1');

        return Json::respond($response, ['status' => 'ok']);
    }
}
