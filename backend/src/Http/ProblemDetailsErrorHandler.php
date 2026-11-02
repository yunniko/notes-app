<?php

declare(strict_types=1);

namespace App\Http;

use App\Domain\Exception\AuthenticationRequired;
use App\Domain\Exception\InvalidCredentials;
use App\Domain\Exception\NoteNotFound;
use App\Domain\Exception\TooManyLoginAttempts;
use App\Domain\Exception\ValidationFailed;
use App\Domain\Exception\VersionConflict;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Throwable;

/**
 * Maps every exception to an RFC 9457 "problem details" JSON response, so clients handle
 * one error shape everywhere. Domain errors get their own status; anything unexpected is
 * logged and returned as a generic 500 that leaks no internals.
 */
final readonly class ProblemDetailsErrorHandler
{
    public function __construct(private ResponseFactoryInterface $responses) {}

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
    ): ResponseInterface {
        [$status, $problem] = match (true) {
            $exception instanceof ValidationFailed => [422, [
                'title' => 'Validation failed',
                'detail' => $exception->getMessage(),
                'errors' => $exception->errors,
            ]],
            $exception instanceof NoteNotFound => [404, [
                'title' => 'Note not found',
                'detail' => $exception->getMessage(),
            ]],
            $exception instanceof AuthenticationRequired => [401, [
                'title' => 'Authentication required',
                'detail' => $exception->getMessage(),
            ]],
            $exception instanceof InvalidCredentials => [401, [
                'title' => 'Login failed',
                'detail' => $exception->getMessage(),
            ]],
            $exception instanceof TooManyLoginAttempts => [429, [
                'title' => 'Too many attempts',
                'detail' => $exception->getMessage(),
            ]],
            $exception instanceof VersionConflict => [409, [
                'title' => 'Edit conflict',
                'detail' => $exception->getMessage(),
                'current' => $exception->current->toArray(),
            ]],
            $exception instanceof HttpException => [$exception->getCode(), [
                'title' => $exception->getTitle(),
                'detail' => $exception->getMessage(),
            ]],
            default => [500, [
                'title' => 'Internal Server Error',
                'detail' => $displayErrorDetails ? $exception->getMessage() : 'Something went wrong on our side.',
            ]],
        };

        if ($status >= 500 && $logErrors) {
            error_log(sprintf('%s: %s', $exception::class, $logErrorDetails ? (string) $exception : $exception->getMessage()));
        }

        $response = Json::respond(
            $this->responses->createResponse(),
            ['type' => 'about:blank', 'status' => $status] + $problem,
            $status,
            Json::PROBLEM_TYPE,
        );

        return $exception instanceof TooManyLoginAttempts
            ? $response->withHeader('Retry-After', (string) $exception->retryAfterSeconds)
            : $response;
    }
}
