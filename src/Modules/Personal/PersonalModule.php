<?php

declare(strict_types=1);

namespace App\Modules\Personal;

use App\Core\Container;
use App\Http\Middleware\Authenticate;
use App\Http\Router;
use App\Modules\Identity\Contract\Authenticator;
use App\Modules\Personal\Application\CalendarFeed;
use App\Modules\Personal\Application\Inbox;
use App\Modules\Personal\Application\Planner;
use App\Modules\Personal\Http\InboxController;
use App\Modules\Personal\Http\PlannerController;
use App\Security\RateLimitRule;
use App\Security\RateLimits;
use App\Shared\Events\EventBus;
use App\Shared\Module\ModuleProvider;
use App\View\View;

/**
 * Things that belong to one person and to no workspace: the inbox (with
 * quick capture from the header or the "q" key), the week planner of work
 * time, and the private calendar (iCal) link.
 */
final class PersonalModule implements ModuleProvider
{
    public function name(): string
    {
        return 'personal';
    }

    public function register(Container $container): void
    {
        $container->extend(\App\Shared\Export\PersonalData::class, function (object $data, Container $c): void {
            /** @var \App\Shared\Export\PersonalData $data */
            $db = fn (): \PDO => $c->get(\PDO::class);
            $rows = static function (\PDO $db, string $sql, int $userId): array {
                $stmt = $db->prepare($sql);
                $stmt->execute([$userId]);

                return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            };
            $data->add('inbox', static fn (int $userId): array => $rows($db(), 'SELECT title, description, due_date, created_at FROM inbox_items WHERE user_id = ?', $userId));
            $data->add('planner', static fn (int $userId): array => $rows($db(), 'SELECT * FROM planner_sessions WHERE user_id = ?', $userId));
            $data->eraseWith('personal', static fn (int $userId) => \App\Shared\Export\PersonalData::deleteRows($db(), $userId, ['inbox_items', 'planner_sessions', 'calendar_feeds']));
        });

        $container->singleton(Inbox::class, fn (Container $c) => $c->build(Inbox::class));
        $container->singleton(Planner::class, fn (Container $c) => $c->build(Planner::class));
        $container->singleton(CalendarFeed::class, fn (Container $c) => $c->build(CalendarFeed::class));

        $container->extend(RateLimits::class, function (object $limits): void {
            /** @var RateLimits $limits */
            $limits->add(new RateLimitRule('calendar-feed', 'GET', '/calendar/*', 60, 60));
            $limits->add(new RateLimitRule('inbox-capture', 'POST', '/inbox', 60, 60, 'user'));
        });

        $container->extend(View::class, function (object $view, Container $c): void {
            /** @var View $view */
            $view->addToSlot('header.actions', 'personal/header-actions');
            $view->addToSlot('card.side', 'personal/card-planner');
            $userId = static fn (): int => $c->get(Authenticator::class)->user()?->id ?? 0;

            $view->composer('personal/header-actions', fn (array $data): array => [
                'inboxCount' => $c->get(Inbox::class)->count($userId()),
            ]);
            $view->composer('personal/card-planner', fn (array $data): array => [
                'planned'   => $c->get(Planner::class)->upcomingForCard($userId(), $data['card']->id),
                'durations' => Planner::DURATIONS,
            ]);
        });
    }

    public function routes(Router $router): void
    {
        $router->group([Authenticate::class], function (Router $router): void {
            $router->get('/inbox', [InboxController::class, 'index']);
            $router->post('/inbox', [InboxController::class, 'store']);
            $router->post('/inbox/{item:ulid}', [InboxController::class, 'update']);
            $router->post('/inbox/{item:ulid}/delete', [InboxController::class, 'destroy']);
            $router->post('/inbox/{item:ulid}/files', [InboxController::class, 'addFile']);
            $router->post('/inbox/{item:ulid}/move', [InboxController::class, 'move']);
            $router->get('/inbox-files/{file:ulid}', [InboxController::class, 'file']);
            $router->post('/inbox-files/{file:ulid}/delete', [InboxController::class, 'removeFile']);

            $router->get('/planner', [PlannerController::class, 'index']);
            $router->post('/planner/sessions', [PlannerController::class, 'plan']);
            $router->post('/planner/sessions/{id}', [PlannerController::class, 'move']);
            $router->post('/planner/sessions/{id}/delete', [PlannerController::class, 'remove']);
            $router->post('/planner/calendar-link', [PlannerController::class, 'renewLink']);
            $router->post('/planner/calendar-link/delete', [PlannerController::class, 'revokeLink']);
        });

        // Calendar apps fetch this without a session; the secret in the path is checked instead.
        $router->get('/calendar/{token:token}.ics', [PlannerController::class, 'feed']);
    }

    public function listeners(EventBus $events, Container $container): void
    {
    }

    public function migrationsPath(): ?string
    {
        return __DIR__ . '/Infrastructure/Migrations';
    }
}
