<?php

declare(strict_types=1);

namespace App\Modules\Notification;

use App\Shared\Automation\ActionContext;
use App\Shared\Automation\ActionType;
use App\Shared\Automation\AutomationActions;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Notification\Application\Notifier;
use App\Core\Container;
use App\Http\Middleware\Authenticate;
use App\Http\Router;
use App\Modules\Identity\Contract\Authenticator;
use App\Modules\Notification\Application\NotificationCenter;
use App\Modules\Notification\Application\NotificationRules;
use App\Modules\Notification\Domain\NotificationStore;
use App\Modules\Notification\Http\NotificationController;
use App\Modules\Notification\Infrastructure\PdoNotifications;
use App\Shared\Events\DomainEvent;
use App\Shared\Events\EventBus;
use App\Shared\Module\ModuleProvider;
use App\Support\Clock;
use App\View\View;
use PDO;

/**
 * Watching cards and boards, the in-app bell, and email for what matters
 * (mentions, assignments, due dates, membership changes).
 * Listens to card, checklist, comment, board member, invitation and workspace events.
 * Adds the bell to the header, and Watch buttons to the card and the board.
 */
final class NotificationModule implements ModuleProvider
{
    public function name(): string
    {
        return 'notification';
    }

    public function register(Container $container): void
    {
        $container->extend(AutomationActions::class, function (object $actions, Container $c): void {
            /** @var AutomationActions $actions */
            $notice = static function (string $who) use ($c): \Closure {
                return static function (ActionContext $ctx, string $message) use ($c, $who): void {
                    $card = $c->get(CardQueries::class)->find((int) $ctx->cardId) ?? throw new \RuntimeException('The card is gone.');
                    $recipients = match ($who) {
                        'managers' => array_keys(array_filter($c->get(AccessGate::class)->peopleOn($card->boardId), static fn (BoardRole $r) => $r === BoardRole::Manager)),
                        'members'  => $c->get(CardQueries::class)->membersForCards([$card->id])[$card->id] ?? [],
                        default    => $c->get(NotificationStore::class)->watchersOfCard($card->id, $card->boardId),
                    };
                    // The person who started it already knows, so they are left out.
                    $c->get(Notifier::class)->notify($recipients, 'automation.notice', $ctx->triggeredBy, $card->boardId, $card->id, $card->title, [
                        'rule'    => $ctx->automationName,
                        'message' => mb_substr(trim($message), 0, 500),
                    ], '/cards/' . $card->publicId, 'automation:' . $ctx->runKey . ':' . $who);
                };
            };
            $actions->add(new ActionType('notify.managers', 'Notify the board managers', 'text', $notice('managers')));
            $actions->add(new ActionType('notify.members', 'Notify the people on the card', 'text', $notice('members')));
            $actions->add(new ActionType('notify.watchers', 'Notify the card\'s watchers', 'text', $notice('watchers')));
        });
        $container->singleton(NotificationStore::class, fn (Container $c) => new PdoNotifications($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(NotificationCenter::class, fn (Container $c) => $c->build(NotificationCenter::class));
        $container->singleton(NotificationRules::class, fn (Container $c) => $c->build(NotificationRules::class));

        $container->extend(View::class, function (object $view, Container $c): void {
            /** @var View $view */
            $view->addToSlot('header.actions', 'notifications/bell');
            $view->addToSlot('card.side', 'notifications/watch-card');
            $view->addToSlot('board.actions', 'notifications/watch-board');

            $userId = static fn (): int => $c->get(Authenticator::class)->user()?->id ?? 0;

            $view->composer('notifications/bell', fn (): array => ['unread' => $c->get(NotificationCenter::class)->unreadCount($userId())]);
            $view->composer('notifications/watch-card', fn (array $data): array => [
                'watching' => $c->get(NotificationCenter::class)->isWatchingCard($userId(), $data['card']->id),
            ]);
            $view->composer('notifications/watch-board', fn (array $data): array => [
                'watching' => $c->get(NotificationCenter::class)->isWatchingBoard($userId(), $data['board']->id),
            ]);
        });
    }

    public function routes(Router $router): void
    {
        $router->group([Authenticate::class], function (Router $router): void {
            $router->get('/notifications', [NotificationController::class, 'index']);
            $router->post('/notifications/read-all', [NotificationController::class, 'readAll']);
            $router->post('/notifications/settings', [NotificationController::class, 'settings']);
            $router->post('/notifications/{id}/open', [NotificationController::class, 'open']);
            $router->post('/cards/{card:ulid}/watch', [NotificationController::class, 'watchCard']);
            $router->post('/boards/{board:ulid}/watch', [NotificationController::class, 'watchBoard']);
        });
    }

    public function listeners(EventBus $events, Container $container): void
    {
        foreach (NotificationRules::events() as $name) {
            $events->listen($name, static function (DomainEvent $event) use ($container): void {
                $container->get(NotificationRules::class)->handle($event);
            });
        }
    }

    public function migrationsPath(): ?string
    {
        return __DIR__ . '/Infrastructure/Migrations';
    }
}
