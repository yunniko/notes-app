<?php

declare(strict_types=1);

namespace App\Http\Action;

use App\Application\NoteService;
use App\Domain\Note;
use App\Http\Json;
use App\Http\Middleware\RequireUser;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** GET /api/notes?priority=high,medium&archived=true */
final readonly class ListNotesAction
{
    public function __construct(private NoteService $notes) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $notes = $this->notes->list(RequireUser::user($request)->id, $query['priority'] ?? null, $query['archived'] ?? null);

        return Json::respond($response, ['data' => array_map(static fn(Note $note): array => $note->toArray(), $notes)]);
    }
}
