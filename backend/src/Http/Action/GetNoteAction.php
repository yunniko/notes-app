<?php

declare(strict_types=1);

namespace App\Http\Action;

use App\Application\NoteService;
use App\Http\Json;
use App\Http\Middleware\RequireUser;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** GET /api/notes/{id} */
final readonly class GetNoteAction
{
    public function __construct(private NoteService $notes) {}

    /** @param array{id: string} $args */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return Json::respond($response, ['data' => $this->notes->get(RequireUser::user($request)->id, (int) $args['id'])->toArray()]);
    }
}
