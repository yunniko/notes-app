<?php

declare(strict_types=1);

namespace App;

use App\Domain\LoginThrottle;
use App\Domain\NoteRepository;
use App\Domain\SessionRepository;
use App\Domain\UserRepository;
use App\Http\Action\ArchiveNoteAction;
use App\Http\Action\CreateNoteAction;
use App\Http\Action\CurrentUserAction;
use App\Http\Action\DeleteNoteAction;
use App\Http\Action\GetNoteAction;
use App\Http\Action\HealthAction;
use App\Http\Action\ListNotesAction;
use App\Http\Action\LoginAction;
use App\Http\Action\LogoutAction;
use App\Http\Action\RegisterAction;
use App\Http\Action\RestoreNoteAction;
use App\Http\Action\UpdateNoteAction;
use App\Http\Middleware\RequireUser;
use App\Http\Middleware\SameOriginGuard;
use App\Http\ProblemDetailsErrorHandler;
use App\Infrastructure\Database;
use App\Infrastructure\PdoLoginThrottle;
use App\Infrastructure\PdoNoteRepository;
use App\Infrastructure\PdoSessionRepository;
use App\Infrastructure\PdoUserRepository;
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
                UserRepository::class => autowire(PdoUserRepository::class),
                SessionRepository::class => autowire(PdoSessionRepository::class),
                LoginThrottle::class => autowire(PdoLoginThrottle::class),
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
            $api->post('/auth/register', RegisterAction::class);
            $api->post('/auth/login', LoginAction::class);
            $api->post('/auth/logout', LogoutAction::class);

            $api->group('', function (RouteCollectorProxy $user): void {
                $user->get('/auth/me', CurrentUserAction::class);
                $user->get('/notes', ListNotesAction::class);
                $user->post('/notes', CreateNoteAction::class);
                $user->get('/notes/{id:[1-9][0-9]{0,9}}', GetNoteAction::class);
                $user->put('/notes/{id:[1-9][0-9]{0,9}}', UpdateNoteAction::class);
                $user->delete('/notes/{id:[1-9][0-9]{0,9}}', DeleteNoteAction::class);
                $user->post('/notes/{id:[1-9][0-9]{0,9}}/archive', ArchiveNoteAction::class);
                $user->post('/notes/{id:[1-9][0-9]{0,9}}/restore', RestoreNoteAction::class);
            })->add(RequireUser::class);
        })->add(SameOriginGuard::class);

        return $app;
    }
}
