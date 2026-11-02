<?php

declare(strict_types=1);

namespace App\Http\Action;

use App\Application\NoteService;
use App\Http\Json;
use App\Http\Middleware\RequireUser;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** POST /api/notes — 201 with a Location header pointing at the new note. */
final readonly class CreateNoteAction
{
    public function __construct(private NoteService $notes) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $note = $this->notes->create(RequireUser::user($request)->id, Json::body($request));

        return Json::respond($response, ['data' => $note->toArray()], 201)
            ->withHeader('Location', '/api/notes/' . $note->id);
    }
}
