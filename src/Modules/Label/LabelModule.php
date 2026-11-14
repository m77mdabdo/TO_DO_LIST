<?php

declare(strict_types=1);

namespace App\Modules\Label;

use App\Modules\Board\Contract\Events\BoardCopied;
use App\Modules\Board\Contract\Events\BoardSeeded;
use App\Modules\Card\Contract\Events\CardCopied;
use App\Modules\Label\Application\LabelCopier;
use App\Core\Container;
use App\Http\Middleware\Authenticate;
use App\Http\Router;
use App\Modules\Label\Application\LabelService;
use App\Modules\Label\Contract\LabelCatalog;
use App\Modules\Label\Domain\LabelRepository;
use App\Modules\Label\Http\LabelController;
use App\Modules\Label\Infrastructure\PdoLabels;
use App\Shared\Events\EventBus;
use App\Shared\Module\ModuleProvider;
use App\Support\Clock;
use PDO;

/**
 * Colored labels on a board, attached to cards.
 * Publishes: label.attached, label.detached.
 * Offers: Contract\LabelCatalog.
 */
final class LabelModule implements ModuleProvider
{
    public function name(): string
    {
        return 'label';
    }

    public function register(Container $container): void
    {
        $container->singleton(LabelRepository::class, fn (Container $c) => new PdoLabels($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(LabelService::class, fn (Container $c) => $c->build(LabelService::class));
        $container->singleton(LabelCatalog::class, fn (Container $c) => $c->get(LabelService::class));
    }

    public function routes(Router $router): void
    {
        $router->group([Authenticate::class], function (Router $router): void {
            $router->post('/boards/{board:ulid}/labels', [LabelController::class, 'store']);
            $router->post('/cards/{card:ulid}/labels/{id}', [LabelController::class, 'toggle']);
        });
    }

    public function listeners(EventBus $events, Container $container): void
    {
        $events->listen(BoardCopied::name(), static fn (BoardCopied $event) => $container->get(LabelCopier::class)->onBoardCopied($event));
        $events->listen(BoardSeeded::name(), static fn (BoardSeeded $event) => $container->get(LabelCopier::class)->onBoardSeeded($event));
        $events->listen(CardCopied::name(), static fn (CardCopied $event) => $container->get(LabelCopier::class)->onCardCopied($event));
    }

    public function migrationsPath(): ?string
    {
        return __DIR__ . '/Infrastructure/Migrations';
    }
}
