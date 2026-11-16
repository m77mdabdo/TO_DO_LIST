<?php

declare(strict_types=1);

namespace App\Modules\Board\Http;

use App\Controllers\Controller;
use App\Core\Config;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Board\Application\BoardService;
use App\Modules\Board\Contract\SearchQuery;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Board\Domain\SavedSearches;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Label\Contract\LabelCatalog;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Modules\Workspace\Contract\WorkspaceSummary;
use App\Session\Session;
use App\Shared\Search\SearchSources;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use App\View\View;

/**
 * Searches every workspace the user belongs to, or one of them. Only boards
 * the user can see are ever searched, so a result, a saved search or a
 * shared link never reveals a private board: the same link shows each
 * person only what they themselves may see.
 */
final class SearchController extends Controller
{
    public const MAX_RESULTS = 100;

    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private BoardService $boards,
        private BoardDirectory $directory,
        private WorkspaceDirectory $workspaces,
        private CardQueries $cards,
        private LabelCatalog $labels,
        private UserDirectory $users,
        private SearchSources $sources,
        private SavedSearches $saved,
        private Clock $clock,
        private Config $config,
    ) {
        parent::__construct($view, $url, $session);
    }

    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $query = SearchQuery::fromInput(static fn (string $key) => $request->input($key));

        // Scope: my workspaces (or the one asked for), then the boards I can see in them.
        $workspaces = [];
        foreach ($this->workspaces->workspacesOf($user->id) as $workspace) {
            $workspaces[$workspace->id] = $workspace;
        }
        $inScope = $query->workspace === '' ? $workspaces : array_filter($workspaces, static fn (WorkspaceSummary $w) => $w->publicId === $query->workspace);

        $boards = [];
        $workspaceOf = [];
        foreach ($inScope as $workspace) {
            $visible = [...$this->boards->boardsInWorkspace($user->id, $workspace->id), ...($query->archived ? $this->boards->boardsInWorkspace($user->id, $workspace->id, true) : [])];
            foreach ($visible as $board) {
                $boards[$board->id] = $board;
                $workspaceOf[$board->id] = $workspace;
            }
        }
        $searched = $query->board !== '' ? array_filter($boards, static fn (BoardSummary $b) => $b->publicId === $query->board) : $boards;
        $chosenBoard = $query->board !== '' ? (array_values($searched)[0] ?? null) : null;

        [$cards, $total] = $query->isActive() ? $this->findCards($query, array_keys($searched), $user->id) : [[], 0];
        $hits = $query->hasText() ? $this->sources->search(array_keys($searched), $query->q, 500) : [];
        $members = $this->cards->membersForCards(array_map(static fn (CardSummary $c) => $c->id, $cards));

        $peopleIds = [];
        foreach ($inScope as $workspace) {
            foreach ($this->workspaces->members($workspace->id) as $member) {
                $peopleIds[] = $member['userId'];
            }
        }

        return $this->render('search/index', [
            'title'        => 'Search',
            'query'        => $query,
            'cards'        => $cards,
            'total'        => $total,
            'hits'         => $hits,
            'boards'       => $query->hasText() ? array_values(array_filter($searched, static fn (BoardSummary $b) => mb_stripos($b->name, $query->q) !== false)) : [],
            'boardById'    => $boards,
            'workspaceOf'  => $workspaceOf,
            'workspaces'   => array_values($workspaces),
            'boardChoices' => array_values(array_filter($boards, static fn (BoardSummary $b) => !$b->archived)),
            'listChoices'  => $chosenBoard !== null ? $this->directory->openLists($chosenBoard->id) : [],
            'cardLabels'   => $this->labels->forCards(array_map(static fn (CardSummary $c) => $c->id, $cards)),
            'cardMembers'  => $members,
            'people'       => $this->users->findMany(array_values(array_unique([...$peopleIds, ...array_merge([], ...array_values($members))]))),
            'saved'        => $this->saved->forUser($user->id),
            'shareLink'    => $this->absolute('/search' . ($query->queryString() !== '' ? '?' . $query->queryString() : '')),
            'searchQuery'  => $query->q,
        ]);
    }

    /** Body: name, query (the search's query string). */
    public function save(Request $request): Response
    {
        $user = $this->user($request);
        $query = SearchQuery::fromQueryString($request->input('query'));
        $back = '/search' . ($query->queryString() !== '' ? '?' . $query->queryString() : '');

        try {
            $name = $this->validName($request->input('name'));
            if (!$query->isActive() && $query->workspace === '' && $query->board === '') {
                throw $this->invalid('name', 'Search for something first, then save it.');
            }
            if (count($this->saved->forUser($user->id)) >= SavedSearches::MAX_PER_USER) {
                throw $this->invalid('name', 'You can keep at most 50 saved searches. Delete one first.');
            }
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $back);
        }

        $this->saved->create($user->id, $name, $query->queryString());

        return $this->redirect($back, 'Search saved.');
    }

    /** Body: name, and optionally query to replace the saved filters with the current ones. @param array{saved: string} $params */
    public function update(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $saved = $this->saved->find($user->id, $params['saved']) ?? throw HttpException::notFound();
        $queryString = array_key_exists('query', $request->body()) ? SearchQuery::fromQueryString($request->input('query'))->queryString() : $saved['query'];

        try {
            $name = $this->validName($request->input('name'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/search?' . $saved['query']);
        }

        $this->saved->update($saved['id'], $name, $queryString);

        return $this->redirect('/search' . ($queryString !== '' ? '?' . $queryString : ''), 'Saved search updated.');
    }

    /** @param array{saved: string} $params */
    public function delete(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $saved = $this->saved->find($user->id, $params['saved']) ?? throw HttpException::notFound();
        $this->saved->delete($saved['id']);

        return $this->redirect('/search', 'Saved search deleted.');
    }

    /**
     * Matching cards, newest match first, after every filter.
     *
     * @param list<int> $boardIds boards the user may see
     * @return array{0: list<CardSummary>, 1: int} the first MAX_RESULTS and how many matched
     */
    private function findCards(SearchQuery $query, array $boardIds, int $userId): array
    {
        if ($boardIds === []) {
            return [[], 0];
        }

        if ($query->hasText()) {
            $hits = $this->sources->search($boardIds, $query->q, 500);
            $found = $this->cards->findMany(array_keys($hits));
            $cards = [];
            foreach (array_keys($hits) as $id) {
                if (isset($found[$id])) {
                    $cards[] = $found[$id];
                }
            }
        } else {
            // Filters only (e.g. "mine, due this week"): open cards of the boards in scope.
            $cards = $this->cards->openCardsOnBoards($boardIds, 2000);
        }

        $ids = array_map(static fn (CardSummary $c) => $c->id, $cards);
        $labels = $query->label !== '' ? $this->labels->forCards($ids) : [];
        $members = $this->cards->membersForCards($ids);
        $now = $this->clock->now();

        $cards = array_values(array_filter($cards, function (CardSummary $card) use ($query, $labels, $members, $userId, $now): bool {
            if ($card->archived && !$query->archived) {
                return false;
            }
            if ($query->list !== null && $card->listId !== $query->list) {
                return false;
            }
            if ($query->label !== '') {
                $names = array_map(static fn ($l) => mb_strtolower($l->name), $labels[$card->id] ?? []);
                if (!in_array(mb_strtolower($query->label), $names, true)) {
                    return false;
                }
            }

            return $query->filter->matches($card, [], $members[$card->id] ?? [], $userId, $now);
        }));

        return [array_slice($cards, 0, self::MAX_RESULTS), count($cards)];
    }

    private function validName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw $this->invalid('name', 'Give the search a name (up to 80 characters).');
        }

        return $name;
    }

    private function invalid(string $field, string $message): ValidationException
    {
        return new ValidationException(new ValidationResult([$field => $message]));
    }

    private function absolute(string $path): string
    {
        // The address people open the app at, so the link works when pasted elsewhere.
        return rtrim((string) $this->config->get('url', ''), '/') . $path;
    }
}
