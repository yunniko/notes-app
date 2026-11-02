<?php

declare(strict_types=1);

namespace App\Http\Action;

use App\Application\NoteService;
use App\Domain\Note;
use App\Http\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** GET /api/notes?priority=high,medium */
final readonly class ListNotesAction
{
    public function __construct(private NoteService $notes) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $notes = $this->notes->list($request->getQueryParams()['priority'] ?? null);

        return Json::respond($response, ['data' => array_map(static fn(Note $note): array => $note->toArray(), $notes)]);
    }
}
