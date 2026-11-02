<?php

declare(strict_types=1);

namespace App\Http;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpException;

/** Reading JSON request bodies and writing JSON responses, in one place. */
final class Json
{
    public const string PROBLEM_TYPE = 'application/problem+json';

    /**
     * Decodes the request body. Requires `Content-Type: application/json`, which also means a
     * cross-site HTML form cannot post here without a CORS preflight.
     *
     * @throws HttpException 415 for another content type, 400 for malformed JSON
     */
    public static function body(ServerRequestInterface $request): mixed
    {
        $type = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0]));
        if ($type !== 'application/json') {
            $exception = new HttpException($request, 'Send the request body as application/json.', 415);
            $exception->setTitle('Unsupported Media Type');

            throw $exception;
        }

        try {
            return json_decode((string) $request->getBody(), true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new HttpBadRequestException($request, 'The request body is not valid JSON.', $e);
        }
    }

    public static function respond(
        ResponseInterface $response,
        mixed $data,
        int $status = 200,
        string $contentType = 'application/json',
    ): ResponseInterface {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $response->withStatus($status)->withHeader('Content-Type', $contentType);
    }
}
