<?php

declare(strict_types=1);

namespace App\Modules\Integration\Http;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Card\Contract\CardWriter;
use App\Modules\Identity\Contract\User;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Integration\Application\ApiTokens;
use App\Modules\Integration\Contract\ApiToken;
use App\Modules\Label\Contract\LabelCatalog;
use App\Validation\ValidationException;

/**
 * A small JSON API, version 1. Every call is limited to the token's boards
 * and scope and to what its owner may do. Errors are JSON with a message.
 */
final class ApiController
{
    public function __construct(
        private ApiTokens $tokens,
        private BoardDirectory $boards,
        private CardQueries $cards,
        private CardWriter $writer,
        private LabelCatalog $labels,
        private UserDirectory $users,
    ) {
    }

    public function me(Request $request): Response
    {
        $token = $this->token($request);
        /** @var User $user */
        $user = $request->attribute('user');

        return Response::json(['user' => ['name' => $user->name], 'token' => ['name' => $token->name, 'scope' => $token->scope, 'expires_at' => $token->expiresAt]]);
    }

    public function boards(Request $request): Response
    {
        $token = $this->token($request);
        $out = [];
        foreach ($token->boardIds as $boardId) {
            $board = $this->boards->find($boardId);
            if ($board !== null && $this->tokens->allows($token, $boardId, Permission::ViewBoard)) {
                $out[] = [
                    'id'    => $board->publicId,
                    'name'  => $board->name,
                    'lists' => array_map(static fn ($l) => ['id' => $l->id, 'name' => $l->name], $this->boards->openLists($board->id)),
                ];
            }
        }

        return Response::json(['boards' => $out]);
    }

    /** @param array{board: string} $params */
    public function cards(Request $request, array $params): Response
    {
        $board = $this->board($request, $params['board'], Permission::ViewBoard);
        $lists = array_column(array_map(static fn ($l) => ['id' => $l->id, 'name' => $l->name], $this->boards->openLists($board->id)), 'name', 'id');
        $all = array_merge([], ...array_values($this->cards->openCardsByList($board->id)));
        $ids = array_map(static fn (CardSummary $c) => $c->id, $all);
        $labels = $this->labels->forCards($ids);
        $members = $this->cards->membersForCards($ids);
        $people = $this->users->findMany(array_values(array_unique(array_merge([], ...array_values($members)))));

        return Response::json(['cards' => array_map(fn (CardSummary $c) => $this->card($c, $lists, $labels, $members, $people), $all)]);
    }

    /** JSON body: list (id or name), title, description, due_date (Y-m-d). @param array{board: string} $params */
    public function createCard(Request $request, array $params): Response
    {
        $board = $this->board($request, $params['board'], Permission::EditCards);
        $body = $request->body();
        $lists = $this->boards->openLists($board->id);
        $wanted = (string) ($body['list'] ?? '');
        $list = null;
        foreach ($lists as $candidate) {
            if ((string) $candidate->id === $wanted || mb_strtolower($candidate->name) === mb_strtolower($wanted)) {
                $list = $candidate;
                break;
            }
        }
        if ($list === null) {
            return Response::json(['ok' => false, 'message' => 'Unknown list.', 'errors' => ['list' => 'Unknown list.']], 422);
        }

        try {
            $card = $this->writer->createCard($request->attribute('user')->id, $board->id, $list->id, (string) ($body['title'] ?? ''), [
                'description' => is_string($body['description'] ?? null) ? $body['description'] : null,
                'due_date'    => is_string($body['due_date'] ?? null) ? $body['due_date'] : null,
            ]);
        } catch (ValidationException $e) {
            return Response::json(['ok' => false, 'message' => current($e->errors()), 'errors' => $e->errors()], 422);
        }

        return Response::json(['card' => $this->card($card, [$list->id => $list->name], [], [], [])], 201);
    }

    /**
     * @param array<int, string> $lists
     * @param array<int, list<\App\Modules\Label\Contract\Label>> $labels
     * @param array<int, list<int>> $members
     * @param array<int, User> $people
     * @return array<string, mixed>
     */
    private function card(CardSummary $c, array $lists, array $labels, array $members, array $people): array
    {
        return [
            'id'          => $c->publicId,
            'title'       => $c->title,
            'description' => $c->description,
            'list'        => $lists[$c->listId] ?? null,
            'start'       => $c->startDate(),
            'due'         => $c->dueDate(),
            'complete'    => $c->isComplete(),
            'labels'      => array_map(static fn ($l) => ['name' => $l->name, 'color' => $l->color], $labels[$c->id] ?? []),
            'people'      => array_values(array_filter(array_map(static fn (int $id) => $people[$id]->name ?? null, $members[$c->id] ?? []))),
            'created_at'  => $c->createdAt,
        ];
    }

    private function board(Request $request, string $publicId, Permission $permission): BoardSummary
    {
        $token = $this->token($request);
        $board = $this->boards->findByPublicId($publicId);
        if ($board === null || !$this->tokens->allows($token, $board->id, Permission::ViewBoard)) {
            throw HttpException::notFound('No such board for this token.');
        }
        if (!$this->tokens->allows($token, $board->id, $permission)) {
            throw new HttpException(403, 'This token may only read.');
        }

        return $board;
    }

    private function token(Request $request): ApiToken
    {
        return $request->attribute('apiToken');
    }
}
