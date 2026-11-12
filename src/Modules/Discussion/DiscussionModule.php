<?php

declare(strict_types=1);

namespace App\Modules\Discussion;

use App\Core\Container;
use App\Http\Middleware\Authenticate;
use App\Http\Router;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Discussion\Application\DiscussionService;
use App\Modules\Discussion\Domain\DiscussionRepository;
use App\Modules\Discussion\Domain\Mentions;
use App\Modules\Discussion\Http\DiscussionController;
use App\Modules\Discussion\Infrastructure\PdoDiscussion;
use App\Modules\Identity\Contract\Authenticator;
use App\Modules\Identity\Contract\UserDirectory;
use App\Shared\Events\EventBus;
use App\Shared\Module\ModuleProvider;
use App\Support\Clock;
use App\View\View;
use PDO;

/**
 * Comments (with @mentions) and votes on cards, as each board allows.
 * Publishes: comment.posted, vote.changed.
 * Adds to the card page (slots "card.main" and "card.side") and the board's tiles.
 */
final class DiscussionModule implements ModuleProvider
{
    public function name(): string
    {
        return 'discussion';
    }

    public function register(Container $container): void
    {
        $container->singleton(DiscussionRepository::class, fn (Container $c) => new PdoDiscussion($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(DiscussionService::class, fn (Container $c) => $c->build(DiscussionService::class));

        $container->extend(View::class, function (object $view, Container $c): void {
            /** @var View $view */
            $view->addToSlot('card.main', 'discussion/comments');
            $view->addToSlot('card.side', 'discussion/votes');

            $view->composer('discussion/comments', function (array $data) use ($c): array {
                $service = $c->get(DiscussionService::class);
                $userId = $c->get(Authenticator::class)->user()?->id ?? 0;
                $before = (string) ($data['query']['comments_before'] ?? '');
                $comments = $service->comments($data['card']->id, ctype_digit($before) ? (int) $before : null);

                $people = $c->get(UserDirectory::class)->findMany([
                    ...array_keys($c->get(AccessGate::class)->peopleOn($data['card']->boardId)),
                    ...array_map(static fn ($comment) => $comment->authorId, $comments),
                ]);

                return [
                    'comments'   => $comments,
                    'hasMore'    => count($comments) === DiscussionService::PAGE,
                    'people'     => $people,
                    'handles'    => Mentions::handles(array_map(static fn ($u) => $u->name, $people)),
                    'viewerId'   => $userId,
                    'canComment' => $service->canComment($userId, $data['board']) && !$data['card']->archived,
                    'canModerate' => static fn ($comment) => $service->canDelete($userId, $comment),
                ];
            });

            $view->composer('discussion/votes', function (array $data) use ($c): array {
                $service = $c->get(DiscussionService::class);
                $userId = $c->get(Authenticator::class)->user()?->id ?? 0;
                $voters = $service->voters($data['card']->id);

                return [
                    'voters'  => $c->get(UserDirectory::class)->findMany($voters),
                    'voted'   => in_array($userId, $voters, true),
                    'canVote' => $service->canVote($userId, $data['board']) && !$data['card']->archived,
                    'votingOn' => $data['board']->voting->value !== 'off',
                ];
            });

            // Comment and vote counts on every tile of a board page, from two grouped queries.
            $view->composer('boards/show', function (array $data) use ($c): array {
                $cardIds = [];
                foreach ($data['cardsByList'] ?? [] as $cards) {
                    foreach ($cards as $card) {
                        $cardIds[] = $card->id;
                    }
                }

                $badges = $data['cardBadges'] ?? [];
                foreach ($c->get(DiscussionService::class)->countsForCards($cardIds) as $cardId => ['comments' => $comments, 'votes' => $votes]) {
                    if ($comments > 0) {
                        $badges[$cardId][] = ['icon' => 'comment', 'text' => (string) $comments, 'class' => '', 'title' => 'Comments'];
                    }
                    if ($votes > 0) {
                        $badges[$cardId][] = ['icon' => 'vote', 'text' => (string) $votes, 'class' => '', 'title' => 'Votes'];
                    }
                }

                return ['cardBadges' => $badges];
            });
        });
    }

    public function routes(Router $router): void
    {
        $router->group([Authenticate::class], function (Router $router): void {
            $router->post('/cards/{card:ulid}/comments', [DiscussionController::class, 'store']);
            $router->post('/cards/{card:ulid}/vote', [DiscussionController::class, 'vote']);
            $router->post('/comments/{id}', [DiscussionController::class, 'update']);
            $router->post('/comments/{id}/delete', [DiscussionController::class, 'destroy']);
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
