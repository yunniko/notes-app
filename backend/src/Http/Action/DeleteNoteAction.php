<?php

declare(strict_types=1);

namespace App\Http\Action;

use App\Application\NoteService;
use App\Http\Middleware\RequireUser;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** DELETE /api/notes/{id} — removes the note permanently: 204, or 404 when there is no such note. */
final readonly class DeleteNoteAction
{
    public function __construct(private NoteService $notes) {}

    /** @param array{id: string} $args */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->notes->delete(RequireUser::user($request)->id, (int) $args['id']);

        return $response->withStatus(204);
    }
}
