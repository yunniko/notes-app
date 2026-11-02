<?php

declare(strict_types=1);

namespace App\Http\Action;

use App\Application\NoteService;
use App\Http\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * PUT /api/notes/{id} — replaces title, content and priority. The body must carry the
 * `version` the client last read; a stale version gets 409 (optimistic locking, D004).
 */
final readonly class UpdateNoteAction
{
    public function __construct(private NoteService $notes) {}

    /** @param array{id: string} $args */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $note = $this->notes->update((int) $args['id'], Json::body($request));

        return Json::respond($response, ['data' => $note->toArray()]);
    }
}
