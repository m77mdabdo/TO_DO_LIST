<?php

declare(strict_types=1);

namespace App\Modules\Attachment;

use App\Modules\Attachment\Infrastructure\PdoAttachmentSearch;
use App\Shared\Search\SearchSources;
use App\Core\Config;
use App\Core\Container;
use App\Http\Middleware\Authenticate;
use App\Http\Router;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Attachment\Application\AttachmentService;
use App\Modules\Attachment\Domain\AttachmentRepository;
use App\Modules\Attachment\Domain\FileStore;
use App\Modules\Attachment\Domain\UploadPolicy;
use App\Modules\Attachment\Http\AttachmentController;
use App\Modules\Attachment\Infrastructure\LocalFileStore;
use App\Modules\Attachment\Infrastructure\PdoAttachments;
use App\Modules\Identity\Contract\Authenticator;
use App\Modules\Identity\Contract\UserDirectory;
use App\Shared\Events\EventBus;
use App\Shared\Module\ModuleProvider;
use App\Support\Clock;
use App\View\View;
use PDO;

/**
 * Files and links on cards. Files live outside the web root and are only
 * served through a permission check.
 * Publishes: attachment.added, attachment.removed.
 * Adds to the card page (slot "card.main") and the board's tiles.
 */
final class AttachmentModule implements ModuleProvider
{
    public function name(): string
    {
        return 'attachment';
    }

    public function register(Container $container): void
    {
        $container->extend(SearchSources::class, function (object $sources, Container $c): void {
            /** @var SearchSources $sources */
            $sources->add(new PdoAttachmentSearch($c->get(PDO::class)));
        });
        $container->singleton(AttachmentRepository::class, fn (Container $c) => new PdoAttachments($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(FileStore::class, fn (Container $c) => new LocalFileStore(
            (string) $c->get(Config::class)->get('attachments.path', dirname(__DIR__, 3) . '/storage/attachments'),
        ));
        $container->singleton(UploadPolicy::class, fn () => new UploadPolicy());
        $container->singleton(AttachmentService::class, fn (Container $c) => $c->build(AttachmentService::class));

        $container->extend(View::class, function (object $view, Container $c): void {
            /** @var View $view */
            $view->addToSlot('card.main', 'attachments/list');

            $view->composer('attachments/list', function (array $data) use ($c): array {
                $service = $c->get(AttachmentService::class);
                $userId = $c->get(Authenticator::class)->user()?->id ?? 0;
                $attachments = $service->forCard($data['card']->id);

                return [
                    'attachments' => $attachments,
                    'people'      => $c->get(UserDirectory::class)->findMany(array_values(array_unique(array_map(static fn ($a) => $a->uploaderId, $attachments)))),
                    'canAdd'      => $service->canAdd($userId, $data['card']),
                    'canRemove'   => static fn ($attachment) => $service->canRemove($userId, $attachment),
                    'accept'      => implode(',', array_map(static fn (string $ext) => '.' . $ext, $c->get(UploadPolicy::class)->extensions())),
                ];
            });

            // A paperclip with the count on board tiles, from one grouped query.
            $view->composer('boards/show', function (array $data) use ($c): array {
                $cardIds = [];
                foreach ($data['cardsByList'] ?? [] as $cards) {
                    foreach ($cards as $card) {
                        $cardIds[] = $card->id;
                    }
                }

                $badges = $data['cardBadges'] ?? [];
                foreach ($c->get(AttachmentService::class)->countsForCards($cardIds) as $cardId => $count) {
                    $badges[$cardId][] = ['icon' => 'paperclip', 'text' => (string) $count, 'class' => '', 'title' => 'Attachments'];
                }

                return ['cardBadges' => $badges];
            });
        });
    }

    public function routes(Router $router): void
    {
        $router->group([Authenticate::class], function (Router $router): void {
            $router->post('/cards/{card:ulid}/attachments', [AttachmentController::class, 'upload']);
            $router->post('/cards/{card:ulid}/links', [AttachmentController::class, 'link']);
            $router->get('/attachments/{attachment:ulid}', [AttachmentController::class, 'show']);
            $router->post('/attachments/{attachment:ulid}/delete', [AttachmentController::class, 'destroy']);
        });
    }

    public function listeners(EventBus $events, Container $container): void
    {
    }

    public function migrationsPath(): ?string
    {
        return __DIR__ . '/Infrastructure/Migrations';
    }
}
