<?php

declare(strict_types=1);

namespace App\Modules\Automation;

use App\Core\Container;
use App\Http\Middleware\Authenticate;
use App\Http\Router;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Automation\Application\AutomationEngine;
use App\Modules\Automation\Application\AutomationService;
use App\Modules\Automation\Domain\AutomationRepository;
use App\Modules\Automation\Domain\RunLog;
use App\Modules\Automation\Http\AutomationController;
use App\Modules\Automation\Infrastructure\PdoAutomations;
use App\Modules\Automation\Infrastructure\PdoRunLog;
use App\Modules\Card\Contract\Events\CardChanged;
use App\Modules\Card\Contract\Events\CardCreated;
use App\Modules\Card\Contract\Events\CardMemberChanged;
use App\Modules\Card\Contract\Events\CardMoved;
use App\Modules\Checklist\Contract\Events\ChecklistChanged;
use App\Modules\Discussion\Contract\Events\CommentPosted;
use App\Modules\Identity\Contract\Authenticator;
use App\Modules\Label\Contract\Events\LabelAttached;
use App\Shared\Events\DomainEvent;
use App\Shared\Events\EventBus;
use App\Shared\Module\ModuleProvider;
use App\Support\Clock;
use App\View\View;
use PDO;

/**
 * Automations: rules (trigger, conditions, actions), card and board buttons,
 * schedules and due-date automations, with a run log. The actions come from
 * the other modules through App\Shared\Automation\AutomationActions.
 * Listens to: card, label, checklist and comment events.
 * Adds to the board bar (slot "board.actions") and the card page (slot "card.side").
 */
final class AutomationModule implements ModuleProvider
{
    public function name(): string
    {
        return 'automation';
    }

    public function register(Container $container): void
    {
        $container->singleton(AutomationRepository::class, fn (Container $c) => new PdoAutomations($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(RunLog::class, fn (Container $c) => new PdoRunLog($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(AutomationEngine::class, fn (Container $c) => $c->build(AutomationEngine::class));
        $container->singleton(AutomationService::class, fn (Container $c) => $c->build(AutomationService::class));

        $container->extend(View::class, function (object $view, Container $c): void {
            /** @var View $view */
            $view->addToSlot('board.actions', 'automation/board-actions');
            $view->addToSlot('card.side', 'automation/card-buttons');

            $view->composer('automation/board-actions', function (array $data) use ($c): array {
                $userId = $c->get(Authenticator::class)->user()?->id ?? 0;
                $service = $c->get(AutomationService::class);
                $open = !$data['board']->archived;

                return [
                    'canManage' => $open && $service->canManage($userId, $data['board']),
                    'buttons'   => $open && $service->canPress($userId, $data['board']->id) ? $service->buttons($data['board']->id, 'board_button') : [],
                ];
            });
            $view->composer('automation/card-buttons', function (array $data) use ($c): array {
                $userId = $c->get(Authenticator::class)->user()?->id ?? 0;
                $service = $c->get(AutomationService::class);
                $usable = !$data['board']->archived && !$data['card']->archived && $c->get(AccessGate::class)->can($userId, $data['card']->boardId, Permission::EditCards);

                return ['buttons' => $usable ? $service->buttons($data['card']->boardId, 'card_button') : []];
            });
        });
    }

    public function routes(Router $router): void
    {
        $router->group([Authenticate::class], function (Router $router): void {
            $router->get('/boards/{board:ulid}/automation', [AutomationController::class, 'index']);
            $router->post('/boards/{board:ulid}/automation', [AutomationController::class, 'store']);
            $router->post('/automations/{automation:ulid}/toggle', [AutomationController::class, 'toggle']);
            $router->post('/automations/{automation:ulid}/delete', [AutomationController::class, 'destroy']);
            $router->post('/automations/{automation:ulid}/run', [AutomationController::class, 'pressBoard']);
            $router->post('/cards/{card:ulid}/buttons/{automation:ulid}', [AutomationController::class, 'pressCard']);
        });
    }

    public function listeners(EventBus $events, Container $container): void
    {
        $handle = static function (DomainEvent $event) use ($container): void {
            $container->get(AutomationEngine::class)->handle($event);
        };

        foreach ([CardCreated::class, CardMoved::class, CardChanged::class, CardMemberChanged::class, LabelAttached::class, ChecklistChanged::class, CommentPosted::class] as $event) {
            $events->listen($event::name(), $handle);
        }
    }

    public function migrationsPath(): ?string
    {
        return __DIR__ . '/Infrastructure/Migrations';
    }
}
