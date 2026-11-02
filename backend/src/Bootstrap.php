<?php

declare(strict_types=1);

namespace App;

use App\Domain\NoteRepository;
use App\Http\Action\CreateNoteAction;
use App\Http\Action\GetNoteAction;
use App\Http\Action\HealthAction;
use App\Http\Action\ListNotesAction;
use App\Http\Action\UpdateNoteAction;
use App\Http\ProblemDetailsErrorHandler;
use App\Infrastructure\Database;
use App\Infrastructure\PdoNoteRepository;
use DI\ContainerBuilder;
use PDO;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Routing\RouteCollectorProxy;

use function DI\autowire;
use function DI\factory;

/** Wires the container, middleware and routes. Used by public/index.php and the HTTP tests. */
final class Bootstrap
{
    /** @param array<string, mixed> $overrides container definitions to replace (tests) */
    public static function container(array $overrides = []): ContainerInterface
    {
        return new ContainerBuilder()
            ->useAutowiring(true)
            ->addDefinitions([
                PDO::class => factory(static fn(): PDO => Database::fromEnvironment()->connect()),
                NoteRepository::class => autowire(PdoNoteRepository::class),
                ResponseFactoryInterface::class => autowire(ResponseFactory::class),
            ], $overrides)
            ->build();
    }

    /** @return App<ContainerInterface> */
    public static function app(?ContainerInterface $container = null): App
    {
        $container ??= self::container();
        $app = AppFactory::createFromContainer($container);

        $app->addRoutingMiddleware();
        $debug = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOL);
        $app->addErrorMiddleware($debug, true, $debug)
            ->setDefaultErrorHandler(new ProblemDetailsErrorHandler($app->getResponseFactory()));

        $app->group('/api', function (RouteCollectorProxy $api): void {
            $api->get('/health', HealthAction::class);
            $api->get('/notes', ListNotesAction::class);
            $api->post('/notes', CreateNoteAction::class);
            $api->get('/notes/{id:[1-9][0-9]{0,9}}', GetNoteAction::class);
            $api->put('/notes/{id:[1-9][0-9]{0,9}}', UpdateNoteAction::class);
        });

        return $app;
    }
}
