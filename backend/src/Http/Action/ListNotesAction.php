<?php

declare(strict_types=1);

namespace App\Http\Action;

use App\Application\NoteService;
use App\Domain\Note;
use App\Http\Json;
use App\Http\Middleware\RequireUser;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /api/notes?priority=high,medium&archived=true&q=budget+meeting&sort=priority
 *
 * `meta.counts` carries the size of both lists, unfiltered, for the archive link (D016).
 */
final readonly class ListNotesAction
{
    public function __construct(private NoteService $notes) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $ownerId = RequireUser::user($request)->id;
        $notes = $this->notes->list($ownerId, $request->getQueryParams());

        return Json::respond($response, [
            'data' => array_map(static fn(Note $note): array => $note->toArray(), $notes),
            'meta' => ['counts' => $this->notes->counts($ownerId)->toArray()],
        ]);
    }
}
