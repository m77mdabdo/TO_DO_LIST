<?php

declare(strict_types=1);

namespace App\Modules\Discussion\Http;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Discussion\Application\DiscussionService;
use App\Modules\Discussion\Contract\Reactions;
use App\Modules\Identity\Contract\UserDirectory;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

final class DiscussionController extends Controller
{
    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private DiscussionService $discussion,
        private CardQueries $cards,
        private AccessGate $gate,
        private UserDirectory $users,
    ) {
        parent::__construct($view, $url, $session);
    }

    /**
     * Body: body, and optionally a file.
     *
     * @param array{card: string} $params
     */
    public function store(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $card = $this->card($user->id, $params['card']);
        $body = $request->raw('body');

        try {
            $id = $this->discussion->post($user->id, $card, $body, $request->file('file'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $this->cardPath($card) . '#comment-form');
        }

        // Mentioned people who could not be told are shown under the form, with the reason.
        $unreached = $this->discussion->unreachedMentions($card->boardId, $body, $user->id);
        if ($unreached !== null) {
            $this->session->flash('mentions.unreached', $unreached);
        }

        return $this->redirect($this->cardPath($card) . '#comment-' . $id);
    }

    /**
     * Body: emoji (a key of Reactions::ALL). Adds the reaction or takes it back.
     *
     * @param array{id: int} $params
     */
    public function react(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $card = $this->cardOfComment($user->id, $params['id']);

        try {
            [$comment, $on] = $this->discussion->react($user->id, $params['id'], $request->input('emoji'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $this->cardPath($card) . '#comment-' . $params['id']);
        }

        if (!$request->wantsJson()) {
            return $this->redirect($this->cardPath($card) . '#comment-' . $comment->id);
        }

        $people = $this->users->findMany(array_merge(...array_values($comment->reactions ?: [[]])));
        $reactions = [];
        foreach ($comment->reactions as $key => $userIds) {
            if (Reactions::exists($key)) {
                $reactions[$key] = [
                    'count' => count($userIds),
                    'mine'  => in_array($user->id, $userIds, true),
                    'names' => array_values(array_map(static fn (int $id) => $people[$id]->name ?? '', $userIds)),
                ];
            }
        }

        return Response::json(['ok' => true, 'on' => $on, 'reactions' => $reactions]);
    }

    /**
     * POST marks the user as writing a comment; GET lists who else is.
     *
     * @param array{card: string} $params
     */
    public function typing(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $card = $this->card($user->id, $params['card']);

        if ($request->isPost()) {
            $this->discussion->typing($user->id, $card);

            return Response::json(['ok' => true]);
        }

        $people = $this->users->findMany($this->discussion->typingOn($user->id, $card));

        return Response::json(['ok' => true, 'names' => array_values(array_map(static fn ($u) => $u->name, $people))])
            ->withHeader('Cache-Control', 'no-store');
    }

    /**
     * Body: body.
     *
     * @param array{id: int} $params
     */
    public function update(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $card = $this->cardOfComment($user->id, $params['id']);

        try {
            $this->discussion->edit($user->id, $params['id'], $request->raw('body'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $this->cardPath($card) . '#comments');
        }

        return $this->redirect($this->cardPath($card) . '#comment-' . $params['id']);
    }

    /** @param array{id: int} $params */
    public function destroy(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $card = $this->cardOfComment($user->id, $params['id']);
        $this->discussion->delete($user->id, $params['id']);

        return $this->redirect($this->cardPath($card) . '#comments', 'Comment deleted.');
    }

    /** @param array{card: string} $params */
    public function vote(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $card = $this->card($user->id, $params['card']);
        $voted = $this->discussion->toggleVote($user->id, $card);

        return $request->wantsJson()
            ? Response::json(['ok' => true, 'voted' => $voted, 'votes' => count($this->discussion->voters($card->id))])
            : $this->redirect($this->cardPath($card));
    }

    private function card(int $userId, string $publicId): CardSummary
    {
        $card = $this->cards->findByPublicId($publicId) ?? throw AccessDenied::hidden();
        $this->gate->authorize($userId, $card->boardId, Permission::ViewBoard);

        return $card;
    }

    private function cardOfComment(int $userId, int $commentId): CardSummary
    {
        $comment = $this->discussion->visibleComment($userId, $commentId);

        return $this->cards->find($comment->cardId) ?? throw AccessDenied::hidden();
    }

    private function cardPath(CardSummary $card): string
    {
        return '/cards/' . $card->publicId;
    }
}
