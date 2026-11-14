<?php

declare(strict_types=1);

namespace App\Modules\Board\Http;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Board\Application\BoardService;
use App\Modules\Board\Contract\BoardStats;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Board\Contract\CardFilter;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Label\Contract\LabelCatalog;
use App\Session\Session;
use App\Support\Clock;
use App\Support\MonthGrid;
use App\View\View;

/**
 * Other ways to look at one board: a sortable table, a month calendar and a
 * dashboard of numbers. They read the same cards as the board page and take
 * the same filters, so a filtered link works in every view.
 */
final class BoardViewsController extends Controller
{
    public const SORTS = ['title', 'list', 'start', 'due', 'status'];

    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private BoardService $boards,
        private AccessGate $gate,
        private CardQueries $cards,
        private LabelCatalog $labels,
        private UserDirectory $users,
        private Clock $clock,
    ) {
        parent::__construct($view, $url, $session);
    }

    /** Query: the board filters, plus sort (title|list|start|due|status) and dir (asc|desc). @param array{board: string} $params */
    public function table(Request $request, array $params): Response
    {
        $data = $this->load($request, $params['board']);
        $sort = in_array($request->input('sort'), self::SORTS, true) ? $request->input('sort') : 'list';
        $desc = $request->input('dir') === 'desc';

        $listOrder = array_flip(array_map(static fn ($l) => $l->id, $data['lists']));
        $listName = array_column(array_map(static fn ($l) => ['id' => $l->id, 'name' => $l->name], $data['lists']), 'name', 'id');
        $rows = array_merge([], ...array_values($data['cardsByList']));
        $today = $this->clock->now()->format('Y-m-d');
        // Empty dates sort last whichever way, so "due soonest" never starts with undated cards.
        $key = static fn (CardSummary $c): array => match ($sort) {
            'title'  => [0, mb_strtolower($c->title)],
            'start'  => [$c->startDate() === null ? 1 : 0, (string) $c->startDate()],
            'due'    => [$c->dueDate() === null ? 1 : 0, (string) $c->dueDate()],
            'status' => [0, $c->isComplete() ? 2 : ($c->dueDate() !== null && $c->dueDate() < $today ? 0 : 1)],
            default  => [0, $listOrder[$c->listId] ?? 0],
        };
        usort($rows, static function (CardSummary $a, CardSummary $b) use ($key, $desc, $listOrder): int {
            [$emptyA, $valueA] = $key($a);
            [$emptyB, $valueB] = $key($b);
            $order = ($emptyA <=> $emptyB) ?: ($desc ? $valueB <=> $valueA : $valueA <=> $valueB);

            return $order ?: (($listOrder[$a->listId] ?? 0) <=> ($listOrder[$b->listId] ?? 0)) ?: strcmp($a->position, $b->position);
        });

        return $this->render('boards/table', $data + [
            'title'    => $data['board']->name,
            'rows'     => $rows,
            'listName' => $listName,
            'sort'     => $sort,
            'desc'     => $desc,
            'viewPath' => '/table',
        ]);
    }

    /** Query: month (Y-m) and the board filters. @param array{board: string} $params */
    public function calendar(Request $request, array $params): Response
    {
        $data = $this->load($request, $params['board']);
        $locale = $this->view->locale();
        $grid = MonthGrid::for($request->input('month'), $this->clock->now(), $locale === 'ar' ? 6 : 0);

        $byDay = [];
        foreach (array_merge([], ...array_values($data['cardsByList'])) as $card) {
            if ($card->dueDate() !== null && $card->dueDate() >= $grid->first() && $card->dueDate() <= $grid->last()) {
                $byDay[$card->dueDate()][] = ['card' => $card, 'kind' => 'due'];
            }
            if ($card->startDate() !== null && $card->startDate() !== $card->dueDate() && $card->startDate() >= $grid->first() && $card->startDate() <= $grid->last()) {
                $byDay[$card->startDate()][] = ['card' => $card, 'kind' => 'start'];
            }
        }

        return $this->render('boards/calendar', $data + [
            'title'    => $data['board']->name,
            'grid'     => $grid,
            'byDay'    => $byDay,
            'cardById' => array_column(array_map(static fn ($c) => ['id' => $c->id, 'card' => $c], array_merge([], ...array_values($data['cardsByList']))), 'card', 'id'),
            'viewPath' => '/calendar',
        ]);
    }

    public const TIMELINE_DAYS = 35;

    /**
     * Cards as bars from their start to their due date, over five weeks.
     * Query: from (Y-m-d, the first day shown) and the board filters.
     *
     * @param array{board: string} $params
     */
    public function timeline(Request $request, array $params): Response
    {
        $data = $this->load($request, $params['board']);
        $today = $this->clock->now()->setTime(0, 0);
        $firstWeekday = $this->view->locale() === 'ar' ? 6 : 1;
        $from = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->input('from')) ?: null;
        if ($from === null || $from->format('Y-m-d') !== $request->input('from')) {
            $from = $today->modify('-' . (((int) $today->format('w') - $firstWeekday + 7) % 7) . ' days');
        }
        $to = $from->modify('+' . (self::TIMELINE_DAYS - 1) . ' days');
        $first = $from->format('Y-m-d');
        $last = $to->format('Y-m-d');
        $offset = static fn (string $day): int => (int) $from->diff(new \DateTimeImmutable($day))->format('%r%a');

        $rows = [];
        $undated = 0;
        foreach ($data['lists'] as $list) {
            foreach ($data['cardsByList'][$list->id] ?? [] as $card) {
                $start = $card->startDate() ?? $card->dueDate();
                $end = $card->dueDate() ?? $card->startDate();
                if ($start === null || $end === null) {
                    $undated++;
                    continue;
                }
                if ($end < $first || $start > $last) {
                    continue;
                }
                $column = $offset(max($start, $first)) + 1;
                $rows[$list->id][] = [
                    'card'   => $card,
                    'column' => $column,
                    'span'   => $offset(min($end, $last)) + 2 - $column,
                    'before' => $start < $first,
                    'after'  => $end > $last,
                ];
            }
        }

        $days = [];
        for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
            $days[] = $day;
        }

        return $this->render('boards/timeline', $data + [
            'title'    => $data['board']->name,
            'days'     => $days,
            'rows'     => $rows,
            'undated'  => $undated,
            'previous' => $from->modify('-28 days')->format('Y-m-d'),
            'next'     => $from->modify('+28 days')->format('Y-m-d'),
            'todayCol' => $today >= $from && $today <= $to ? $offset($today->format('Y-m-d')) + 1 : null,
            'viewPath' => '/timeline',
        ]);
    }

    /** @param array{board: string} $params */
    public function dashboard(Request $request, array $params): Response
    {
        $data = $this->load($request, $params['board'], filtered: false);

        return $this->render('boards/dashboard', $data + [
            'title' => $data['board']->name,
            'stats' => BoardStats::from($data['lists'], $data['cardsByList'], $data['cardLabels'], $data['cardMembers'], $this->clock->now()),
        ]);
    }

    /**
     * The board, its lists and (filtered) cards, labels and people: the same
     * fixed number of queries as the board page.
     *
     * @return array{board: BoardSummary, role: BoardRole, lists: list<\App\Modules\Board\Contract\ListSummary>, cardsByList: array<int, list<CardSummary>>, cardLabels: array<int, list<\App\Modules\Label\Contract\Label>>, cardMembers: array<int, list<int>>, boardLabels: list<\App\Modules\Label\Contract\Label>, people: array<int, \App\Modules\Identity\Contract\User>, audience: list<int>, filter: CardFilter, totalCards: int, shownCards: int, bodyClass: string, query: array<string, string>}
     */
    private function load(Request $request, string $publicId, bool $filtered = true): array
    {
        $user = $this->user($request);
        $board = $this->boards->boardFor($user->id, $publicId);
        $role = $this->gate->roleOn($user->id, $board->id) ?? BoardRole::Viewer;

        $lists = $this->boards->listsOf($board);
        $cardsByList = $this->cards->openCardsByList($board->id);
        $cardIds = array_map(static fn ($c) => $c->id, array_merge([], ...array_values($cardsByList)));
        $cardMembers = $this->cards->membersForCards($cardIds);
        $cardLabels = $this->labels->forCards($cardIds);
        $audience = array_keys($this->gate->peopleOn($board->id));

        $filter = CardFilter::fromInput(static fn (string $key) => $filtered ? $request->input($key) : '');
        $shown = $filter->apply($cardsByList, $cardLabels, $cardMembers, $user->id, $this->clock->now());

        return [
            'board'       => $board,
            'role'        => $role,
            'lists'       => $lists,
            'cardsByList' => $shown,
            'cardLabels'  => $cardLabels,
            'cardMembers' => $cardMembers,
            'boardLabels' => $this->labels->forBoard($board->id),
            'people'      => $this->users->findMany([...$audience, ...array_merge([], ...array_values($cardMembers))]),
            'audience'    => $audience,
            'filter'      => $filter,
            'totalCards'  => count($cardIds),
            'shownCards'  => array_sum(array_map('count', $shown)),
            'bodyClass'   => 'page-board-view board-' . $board->color,
            'query'       => $filtered ? array_filter($request->only(['q', 'label', 'member', 'due', 'status']), static fn ($v) => $v !== '') : [],
        ];
    }
}
