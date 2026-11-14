<?php

declare(strict_types=1);

namespace App\Modules\Integration;

use App\Core\Config;
use App\Core\Container;
use App\Http\Middleware\Authenticate;
use App\Http\Router;
use App\Modules\Card\Contract\Events\CardChanged;
use App\Modules\Card\Contract\Events\CardCreated;
use App\Modules\Card\Contract\Events\CardMemberChanged;
use App\Modules\Card\Contract\Events\CardMoved;
use App\Modules\Checklist\Contract\Events\ChecklistChanged;
use App\Modules\Discussion\Contract\Events\CommentPosted;
use App\Modules\Integration\Application\Webhooks;
use App\Modules\Integration\Domain\TokenRepository;
use App\Modules\Integration\Domain\UrlGuard;
use App\Modules\Integration\Domain\WebhookRepository;
use App\Modules\Integration\Domain\WebhookTransport;
use App\Modules\Integration\Http\ApiAuthenticate;
use App\Modules\Integration\Http\ApiController;
use App\Modules\Integration\Http\IntegrationController;
use App\Modules\Integration\Infrastructure\CurlTransport;
use App\Modules\Integration\Infrastructure\PdoTokens;
use App\Modules\Integration\Infrastructure\PdoWebhooks;
use App\Modules\Label\Contract\Events\LabelAttached;
use App\Shared\Events\DomainEvent;
use App\Shared\Events\EventBus;
use App\Shared\Module\ModuleProvider;
use App\Support\Clock;
use App\View\View;
use PDO;

/**
 * Integrations: personal API tokens limited to chosen boards and a scope, a
 * small JSON API (/api/v1), and signed outgoing webhooks per board. This is
 * the door for outside tools (Slack, calendars, scripts) without giving them
 * the whole company.
 * Listens to: card, label, checklist and comment events (to queue webhooks).
 */
final class IntegrationModule implements ModuleProvider
{
    public function name(): string
    {
        return 'integration';
    }

    public function register(Container $container): void
    {
        $container->singleton(TokenRepository::class, fn (Container $c) => new PdoTokens($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(WebhookRepository::class, fn (Container $c) => new PdoWebhooks($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(WebhookTransport::class, fn () => new CurlTransport());
        $container->singleton(UrlGuard::class, fn (Container $c) => UrlGuard::system((bool) $c->get(Config::class)->get('webhooks.allow_private_networks', false)));
        $container->singleton(Webhooks::class, fn (Container $c) => $c->build(Webhooks::class));

        $container->extend(View::class, function (object $view, Container $c): void {
            /** @var View $view */
            $view->addToSlot('board.automation-menu', 'integrations/board-link');
        });
    }

    public function routes(Router $router): void
    {
        $router->group([Authenticate::class], function (Router $router): void {
            $router->get('/integrations', [IntegrationController::class, 'tokens']);
            $router->post('/integrations/tokens', [IntegrationController::class, 'createToken']);
            $router->post('/integrations/tokens/{token:ulid}/revoke', [IntegrationController::class, 'revokeToken']);
            $router->get('/boards/{board:ulid}/webhooks', [IntegrationController::class, 'webhooks']);
            $router->post('/boards/{board:ulid}/webhooks', [IntegrationController::class, 'createWebhook']);
            $router->post('/webhooks/{webhook:ulid}/toggle', [IntegrationController::class, 'toggleWebhook']);
            $router->post('/webhooks/{webhook:ulid}/delete', [IntegrationController::class, 'deleteWebhook']);
        });

        $router->group([ApiAuthenticate::class], function (Router $router): void {
            $router->get('/api/v1/me', [ApiController::class, 'me']);
            $router->get('/api/v1/boards', [ApiController::class, 'boards']);
            $router->get('/api/v1/boards/{board:ulid}/cards', [ApiController::class, 'cards']);
            $router->post('/api/v1/boards/{board:ulid}/cards', [ApiController::class, 'createCard']);
        });
    }

    public function listeners(EventBus $events, Container $container): void
    {
        $handle = static function (DomainEvent $event) use ($container): void {
            $container->get(Webhooks::class)->handle($event);
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
