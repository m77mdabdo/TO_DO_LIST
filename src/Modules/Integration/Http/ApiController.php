<?php

declare(strict_types=1);

namespace App\Modules\Integration\Http;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Board\Contract\ListSummary;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Card\Contract\CardWriter;
use App\Modules\Discussion\Contract\Comment;
use App\Modules\Discussion\Contract\CommentWriter;
use App\Modules\Field\Contract\CustomField;
use App\Modules\Field\Contract\FieldValues;
use App\Modules\Identity\Contract\User;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Integration\Application\ApiTokens;
use App\Modules\Integration\Contract\ApiToken;
use App\Modules\Label\Contract\LabelCatalog;
use App\Shared\Ids\Ulid;
use App\Validation\ValidationException;

/**
 * The JSON API, version 1 (see docs/api.md). Every call is limited to the
 * token's boards and scope and to what its owner may do right now. Errors
 * always look like {"ok": false, "error": "<code>", "message": "...",
 * "fields": {...}}; lists are paged with ?limit (1-100) and ?page.
 */
final class ApiController
{
    public const LIMIT = 50;
    public const MAX_LIMIT = 100;

    public function __construct(
        private ApiTokens $tokens,
        private BoardDirectory $boards,
        private CardQueries $cards,
        private CardWriter $writer,
        private LabelCatalog $labels,
        private UserDirectory $users,
        private CommentWriter $comments,
        private FieldValues $fields,
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
                    'lists' => array_map(fn (ListSummary $l) => $this->listOut($l), $this->boards->openLists($board->id)),
                ];
            }
        }
        [$page, $meta] = $this->page($request, $out);

        return Response::json(['boards' => $page] + $meta);
    }

    /** @param array{board: string} $params */
    public function lists(Request $request, array $params): Response
    {
        $board = $this->board($request, $params['board'], Permission::ViewBoard);

        return Response::json(['lists' => array_map(fn (ListSummary $l) => $this->listOut($l), $this->boards->openLists($board->id))]);
    }

    /** Query: list (id or name), limit, page. @param array{board: string} $params */
    public function cards(Request $request, array $params): Response
    {
        $board = $this->board($request, $params['board'], Permission::ViewBoard);
        $lists = $this->listNames($board);
        $byList = $this->cards->openCardsByList($board->id);
        if ($request->input('list') !== '') {
            $list = $this->findList($board, $request->input('list')) ?? throw new ApiError(422, 'invalid', 'Unknown list.', ['list' => 'Unknown list.']);
            $byList = [$list->id => $byList[$list->id] ?? []];
        }
        $all = array_values(array_filter(array_merge([], ...array_values($byList)), static fn (CardSummary $c) => !$c->isSeparator()));
        [$page, $meta] = $this->page($request, $all);

        return Response::json(['cards' => $this->cardsOut($page, $lists)] + $meta);
    }

    /** @param array{card: string} $params */
    public function card(Request $request, array $params): Response
    {
        [$card, $board] = $this->cardOf($request, $params['card'], Permission::ViewBoard);
        $out = $this->cardsOut([$card], $this->listNames($board))[0];
        $values = $this->fields->valuesForCards([$card->id])[$card->id] ?? [];
        $out['fields'] = array_map(static fn (CustomField $f) => [
            'id'    => $f->id,
            'name'  => $f->name,
            'value' => $values[$f->id] ?? null,
            'text'  => isset($values[$f->id]) ? $f->display($values[$f->id]) : null,
        ], $this->fields->forBoard($board->id));

        return Response::json(['card' => $out]);
    }

    /** JSON body: list (id or name), title, description, start_date, due_date (Y-m-d). @param array{board: string} $params */
    public function createCard(Request $request, array $params): Response
    {
        $board = $this->board($request, $params['board'], Permission::EditCards);
        $body = $request->body();
        $list = $this->findList($board, (string) ($body['list'] ?? '')) ?? throw new ApiError(422, 'invalid', 'Unknown list.', ['list' => 'Unknown list.']);

        $card = $this->valid(fn () => $this->writer->createCard($this->userId($request), $board->id, $list->id, $this->str($body, 'title') ?? '', [
            'description' => $this->str($body, 'description'),
            'start_date'  => $this->str($body, 'start_date'),
            'due_date'    => $this->str($body, 'due_date'),
        ]));

        return Response::json(['card' => $this->cardsOut([$card], [$list->id => $list->name])[0]], 201);
    }

    /**
     * Partial update: only the keys sent change (title, description,
     * start_date, due_date; "" or null clears a date).
     *
     * @param array{card: string} $params
     */
    public function updateCard(Request $request, array $params): Response
    {
        [$card, $board] = $this->cardOf($request, $params['card'], Permission::EditCards);
        $body = $request->body();
        $input = [
            'title'       => array_key_exists('title', $body) ? (string) $this->str($body, 'title') : $card->title,
            'description' => array_key_exists('description', $body) ? (string) $this->str($body, 'description') : (string) $card->description,
            'start_date'  => array_key_exists('start_date', $body) ? (string) $this->str($body, 'start_date') : (string) $card->startDate(),
            'due_date'    => array_key_exists('due_date', $body) ? (string) $this->str($body, 'due_date') : (string) $card->dueDate(),
        ];
        $this->valid(fn () => $this->writer->update($this->userId($request), $card, $input));

        return $this->fresh($card, $board);
    }

    /** JSON body: list (id or name). Moves the card to the end of that list. @param array{card: string} $params */
    public function moveCard(Request $request, array $params): Response
    {
        [$card, $board] = $this->cardOf($request, $params['card'], Permission::MoveCards);
        $list = $this->findList($board, (string) ($request->body()['list'] ?? '')) ?? throw new ApiError(422, 'invalid', 'Unknown list.', ['list' => 'Unknown list.']);
        $this->valid(fn () => $this->writer->moveToEnd($this->userId($request), $card, $list->id));

        return $this->fresh($card, $board);
    }

    /** JSON body: archived (true, the default, or false to restore). @param array{card: string} $params */
    public function archiveCard(Request $request, array $params): Response
    {
        [$card, $board] = $this->cardOf($request, $params['card'], Permission::ArchiveItems, true);
        $this->valid(fn () => $this->writer->archive($this->userId($request), $card, $this->flag($request, 'archived')));

        return $this->fresh($card, $board);
    }

    /** JSON body: complete (true, the default, or false to reopen). @param array{card: string} $params */
    public function completeCard(Request $request, array $params): Response
    {
        [$card, $board] = $this->cardOf($request, $params['card'], Permission::EditCards);
        $this->valid(fn () => $this->writer->setComplete($this->userId($request), $card, $this->flag($request, 'complete')));

        return $this->fresh($card, $board);
    }

    /** Newest first, 20 at a time; ?before=<comment id> for the next page. @param array{card: string} $params */
    public function comments(Request $request, array $params): Response
    {
        [$card] = $this->cardOf($request, $params['card'], Permission::ViewBoard, true);
        $before = ctype_digit($request->input('before')) ? (int) $request->input('before') : null;
        $list = $this->comments->comments($card->id, $before);
        $people = $this->users->findMany(array_values(array_unique(array_map(static fn (Comment $c) => $c->authorId, $list))));
        $last = end($list);

        return Response::json([
            'comments' => array_map(static fn (Comment $c) => [
                'id'         => $c->id,
                'author'     => $people[$c->authorId]->name ?? null,
                'body'       => $c->body,
                'created_at' => $c->createdAt,
                'edited_at'  => $c->editedAt,
            ], $list),
            'next_before' => $last !== false && count($list) >= 20 ? $last->id : null,
        ]);
    }

    /** JSON body: body. @param array{card: string} $params */
    public function postComment(Request $request, array $params): Response
    {
        [$card] = $this->cardOf($request, $params['card'], Permission::ViewBoard);
        $this->requireWrite($request);
        $id = $this->valid(fn () => $this->comments->post($this->userId($request), $card, (string) $this->str($request->body(), 'body')));

        return Response::json(['comment' => ['id' => $id]], 201);
    }

    /** @param array{board: string} $params */
    public function fields(Request $request, array $params): Response
    {
        $board = $this->board($request, $params['board'], Permission::ViewBoard);

        return Response::json(['fields' => array_map(static fn (CustomField $f) => [
            'id'      => $f->id,
            'name'    => $f->name,
            'type'    => $f->type,
            'options' => $f->options,
        ], $this->fields->forBoard($board->id))]);
    }

    /** JSON body: fields (object of field id => value; "" clears). @param array{card: string} $params */
    public function fillFields(Request $request, array $params): Response
    {
        [$card, $board] = $this->cardOf($request, $params['card'], Permission::ViewBoard);
        $this->requireWrite($request);
        $input = $request->body()['fields'] ?? null;
        if (!is_array($input)) {
            throw new ApiError(422, 'invalid', 'Send "fields" as an object of field id => value.', ['fields' => 'Send an object of field id => value.']);
        }
        $known = array_map(static fn (CustomField $f) => $f->id, $this->fields->forBoard($board->id));
        foreach (array_keys($input) as $id) {
            if (!in_array((int) $id, $known, true)) {
                throw new ApiError(422, 'invalid', 'Unknown field.', ['fields.' . $id => 'Unknown field.']);
            }
        }
        $this->valid(fn () => $this->fields->fill($this->userId($request), $board, $card, array_map(static fn ($v) => is_scalar($v) ? (string) $v : '', $input)));

        return $this->card($request, $params);
    }

    // ------------------------------------------------------------------

    /**
     * @template T
     * @param callable(): T $action
     * @return T
     */
    private function valid(callable $action): mixed
    {
        try {
            return $action();
        } catch (ValidationException $e) {
            throw new ApiError(422, 'invalid', (string) current($e->errors()), $e->errors());
        } catch (AccessDenied) {
            throw new ApiError(403, 'forbidden', 'The token owner may not do this here.');
        }
    }

    private function fresh(CardSummary $card, BoardSummary $board): Response
    {
        $card = $this->cards->find($card->id) ?? throw new ApiError(404, 'not_found', 'No such card for this token.');

        return Response::json(['card' => $this->cardsOut([$card], $this->listNames($board))[0]]);
    }

    /**
     * @template T
     * @param list<T> $items
     * @return array{0: list<T>, 1: array{page: int, limit: int, total: int, next_page: ?int}}
     */
    private function page(Request $request, array $items): array
    {
        $limit = ctype_digit($request->input('limit')) ? (int) $request->input('limit') : self::LIMIT;
        $page = ctype_digit($request->input('page')) ? (int) $request->input('page') : 1;
        if ($limit < 1 || $limit > self::MAX_LIMIT || $page < 1) {
            throw new ApiError(422, 'invalid', 'limit must be 1-100 and page 1 or more.', ['limit' => 'Use 1 to 100.', 'page' => 'Use 1 or more.']);
        }
        $total = count($items);

        return [array_slice($items, ($page - 1) * $limit, $limit), [
            'page'      => $page,
            'limit'     => $limit,
            'total'     => $total,
            'next_page' => $page * $limit < $total ? $page + 1 : null,
        ]];
    }

    /**
     * @param list<CardSummary> $cards
     * @param array<int, string> $lists
     * @return list<array<string, mixed>>
     */
    private function cardsOut(array $cards, array $lists): array
    {
        $ids = array_map(static fn (CardSummary $c) => $c->id, $cards);
        $labels = $this->labels->forCards($ids);
        $members = $this->cards->membersForCards($ids);
        $people = $this->users->findMany(array_values(array_unique(array_merge([], ...array_values($members)))));

        return array_map(static fn (CardSummary $c) => [
            'id'          => $c->publicId,
            'title'       => $c->title,
            'description' => $c->description,
            'list'        => $lists[$c->listId] ?? null,
            'list_id'     => $c->listId,
            'start'       => $c->startDate(),
            'due'         => $c->dueDate(),
            'complete'    => $c->isComplete(),
            'archived'    => $c->archived,
            'labels'      => array_map(static fn ($l) => ['name' => $l->name, 'color' => $l->color], $labels[$c->id] ?? []),
            'people'      => array_values(array_filter(array_map(static fn (int $id) => $people[$id]->name ?? null, $members[$c->id] ?? []))),
            'created_at'  => $c->createdAt,
        ], $cards);
    }

    /** @return array<string, mixed> */
    private function listOut(ListSummary $list): array
    {
        return ['id' => $list->id, 'name' => $list->name];
    }

    /** @return array<int, string> */
    private function listNames(BoardSummary $board): array
    {
        $out = [];
        foreach ($this->boards->openLists($board->id) as $list) {
            $out[$list->id] = $list->name;
        }

        return $out;
    }

    private function findList(BoardSummary $board, string $wanted): ?ListSummary
    {
        foreach ($this->boards->openLists($board->id) as $candidate) {
            if ((string) $candidate->id === $wanted || mb_strtolower($candidate->name) === mb_strtolower(trim($wanted))) {
                return $candidate;
            }
        }

        return null;
    }

    private function board(Request $request, string $publicId, Permission $permission): BoardSummary
    {
        $token = $this->token($request);
        $board = $this->boards->findByPublicId($publicId);
        if ($board === null || !$this->tokens->allows($token, $board->id, Permission::ViewBoard)) {
            throw new ApiError(404, 'not_found', 'No such board for this token.');
        }
        if (!$this->tokens->allows($token, $board->id, $permission)) {
            throw new ApiError(403, 'forbidden', $token->canWrite() ? 'The token owner may not do this here.' : 'This token may only read.');
        }

        return $board;
    }

    /** @return array{0: CardSummary, 1: BoardSummary} */
    private function cardOf(Request $request, string $publicId, Permission $permission, bool $archivedOk = false): array
    {
        $card = Ulid::isValid($publicId) ? $this->cards->findByPublicId($publicId) : null;
        $board = $card !== null ? $this->boards->find($card->boardId) : null;
        if ($card === null || $board === null || ($card->archived && !$archivedOk) || !$this->tokens->allows($this->token($request), $board->id, Permission::ViewBoard)) {
            throw new ApiError(404, 'not_found', 'No such card for this token.');
        }

        return [$card, $this->board($request, $board->publicId, $permission)];
    }

    private function requireWrite(Request $request): void
    {
        if (!$this->token($request)->canWrite()) {
            throw new ApiError(403, 'forbidden', 'This token may only read.');
        }
    }

    private function flag(Request $request, string $key): bool
    {
        $value = $request->body()[$key] ?? true;

        return !($value === false || $value === 0 || $value === '0' || $value === 'false');
    }

    /** @param array<string, mixed> $body */
    private function str(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;

        return is_string($value) || is_int($value) ? (string) $value : null;
    }

    private function userId(Request $request): int
    {
        return $request->attribute('user')->id;
    }

    private function token(Request $request): ApiToken
    {
        return $request->attribute('apiToken');
    }
}
