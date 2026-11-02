<?php

declare(strict_types=1);

namespace App\Http\Action;

use App\Application\NoteService;
use App\Http\Json;
use App\Http\Middleware\RequireUser;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** POST /api/notes/{id}/archive — hides the note from the main list. Idempotent. */
final readonly class ArchiveNoteAction
{
    public function __construct(private NoteService $notes) {}

    /** @param array{id: string} $args */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $note = $this->notes->archive(RequireUser::user($request)->id, (int) $args['id']);

        return Json::respond($response, ['data' => $note->toArray()]);
    }
}
