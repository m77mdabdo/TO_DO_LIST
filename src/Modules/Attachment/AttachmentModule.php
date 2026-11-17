<?php

declare(strict_types=1);

namespace App\Modules\Attachment;

use App\Shared\Export\ExportParts;
use App\Modules\Attachment\Infrastructure\PdoAttachmentSearch;
use App\Shared\Search\SearchSources;
use App\Core\Config;
use App\Core\Container;
use App\Http\Middleware\Authenticate;
use App\Http\Router;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Attachment\Application\AttachmentService;
use App\Modules\Attachment\Application\BoardBackgrounds;
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
        // The files themselves, for a board's ZIP export: attachments/<card id>/<attachment id>-<name>.
        $container->extend(\App\Shared\Export\ExportFiles::class, function (object $files, Container $c): void {
            /** @var \App\Shared\Export\ExportFiles $files */
            $files->add('attachments', function (array $cardIds) use ($c): array {
                if ($cardIds === []) {
                    return [];
                }
                $stmt = $c->get(\PDO::class)->prepare('SELECT a.public_id, a.name, a.size, a.stored_name, c.public_id AS card FROM attachments a JOIN cards c ON c.id = a.card_id
                    WHERE a.kind = \'file\' AND a.card_id IN (' . implode(',', array_fill(0, count($cardIds), '?')) . ') ORDER BY a.id');
                $stmt->execute(array_values($cardIds));
                $out = [];
                foreach ($stmt->fetchAll() as $row) {
                    $path = $c->get(FileStore::class)->path((string) $row['stored_name']);
                    if ($path !== null && is_file($path)) {
                        // Only safe characters in the archive path, whatever the original name was.
                        $name = preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', (string) $row['name']) ?: 'file';
                        $out[] = ['path' => $path, 'name' => $row['card'] . '/' . $row['public_id'] . '-' . mb_substr($name, 0, 120), 'size' => (int) $row['size']];
                    }
                }

                return $out;
            });
        });

        $container->extend(ExportParts::class, function (object $parts, Container $c): void {
            /** @var ExportParts $parts */
            $parts->add('attachments', function (array $cardIds) use ($c): array {
                $out = [];
                foreach ($cardIds as $cardId) {
                    foreach ($c->get(AttachmentService::class)->forCard($cardId) as $a) {
                        $out[$cardId][] = ['name' => $a->name, 'kind' => $a->kind, 'url' => $a->url, 'type' => $a->mime, 'size' => $a->size, 'created_at' => $a->createdAt];
                    }
                }

                return $out;
            });
        });
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
        $container->singleton(BoardBackgrounds::class, fn (Container $c) => $c->build(BoardBackgrounds::class));
        $container->singleton(\App\Modules\Attachment\Contract\AttachmentFiles::class, fn (Container $c) => $c->get(AttachmentService::class));

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

                // Cover pictures: the attachment's public id, so the tile loads it through the usual permission check.
                $coverIds = [];
                foreach ($data['cardsByList'] ?? [] as $cards) {
                    foreach ($cards as $card) {
                        if ($card->coverAttachmentId !== null) {
                            $coverIds[$card->id] = $card->coverAttachmentId;
                        }
                    }
                }
                $publicIds = $c->get(AttachmentRepository::class)->imagePublicIds(array_values(array_unique($coverIds)));
                $covers = [];
                foreach ($coverIds as $cardId => $attachmentId) {
                    if (isset($publicIds[$attachmentId])) {
                        $covers[$cardId] = '/attachments/' . $publicIds[$attachmentId];
                    }
                }

                return [
                    'cardBadges'     => $badges,
                    'cardCovers'     => $covers,
                    'boardPicture'   => $c->get(BoardBackgrounds::class)->has($data['board']->id) ? '/boards/' . $data['board']->publicId . '/background' : null,
                ];
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
            $router->get('/boards/{board:ulid}/background', [AttachmentController::class, 'background']);
            $router->post('/boards/{board:ulid}/background', [AttachmentController::class, 'uploadBackground']);
            $router->post('/boards/{board:ulid}/background/remove', [AttachmentController::class, 'removeBackground']);
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
