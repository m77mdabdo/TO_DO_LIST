<?php

declare(strict_types=1);

namespace App\Modules\Board\Http;

use App\Controllers\Controller;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Activity\Contract\ActivityFeed;
use App\Modules\Board\Application\BoardService;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Board\Contract\CardFilter;
use App\Modules\Board\Contract\TeamReport;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Checklist\Contract\ChecklistQueries;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Label\Contract\LabelCatalog;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Modules\Workspace\Contract\WorkspaceSummary;
use App\Session\Session;
use App\Support\Clock;
use App\View\View;

/**
 * Views across boards: "My tasks" (everything given to me), every card of a
 * workspace in one table, and the team report. Each only reads boards the
 * person can see, so nothing leaks from a private board.
 */
final class WorkspaceViewsController extends Controller
{
    public const PERIODS = [7, 30, 90];

    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private BoardService $boards,
        private BoardDirectory $directory,
        private WorkspaceDirectory $workspaces,
        private AccessGate $gate,
        private CardQueries $cards,
        private ChecklistQueries $checklists,
        private LabelCatalog $labels,
        private ActivityFeed $activity,
        private UserDirectory $users,
        private Clock $clock,
    ) {
        parent::__construct($view, $url, $session);
    }

    /** Cards and checklist items given to me on every board I can still see, by when they are due. */
    public function myTasks(Request $request): Response
    {
        $user = $this->user($request);
        $visible = fn (int $boardId): bool => ($boards[$boardId] ??= $this->directory->find($boardId)) !== null
            && !$boards[$boardId]->archived && $this->gate->can($user->id, $boardId, Permission::ViewBoard);
        $boards = [];

        $cards = array_values(array_filter($this->cards->openCardsAssignedTo($user->id), fn (CardSummary $c) => $visible($c->boardId)));
        $items = $this->checklists->openItemsAssignedTo($user->id);
        $itemCards = $this->cards->findMany(array_map(static fn ($i) => $i->cardId, $items));
        $items = array_values(array_filter($items, fn ($i) => isset($itemCards[$i->cardId]) && !$itemCards[$i->cardId]->archived && $visible($itemCards[$i->cardId]->boardId)));

        $today = $this->clock->now()->format('Y-m-d');
        $week = $this->clock->now()->modify('+7 days')->format('Y-m-d');
        $group = static fn (?string $due): string => match (true) {
            $due === null  => 'later',
            $due < $today  => 'overdue',
            $due === $today => 'today',
            $due <= $week  => 'week',
            default        => 'later',
        };
        $groups = ['overdue' => [], 'today' => [], 'week' => [], 'later' => []];
        foreach ($cards as $card) {
            $groups[$group($card->dueDate())][] = ['kind' => 'card', 'card' => $card, 'due' => $card->dueDate()];
        }
        foreach ($items as $item) {
            $groups[$group($item->dueDate())][] = ['kind' => 'item', 'item' => $item, 'card' => $itemCards[$item->cardId], 'due' => $item->dueDate()];
        }
        foreach ($groups as &$rows) {
            usort($rows, static fn (array $a, array $b) => [$a['due'] === null, (string) $a['due']] <=> [$b['due'] === null, (string) $b['due']]);
        }
        unset($rows);

        return $this->render('workspaces/my-tasks', [
            'title'  => 'My tasks',
            'groups' => $groups,
            'boards' => array_filter($boards),
            'count'  => count($cards) + count($items),
        ]);
    }

    /** Every open card on the workspace's boards that I can see, with the board filters and a board picker. @param array{workspace: string} $params */
    public function cards(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $workspace = $this->workspace($user->id, $params['workspace']);
        $boards = $this->visibleBoards($user->id, $workspace);
        $chosen = $request->input('board');
        $shownBoards = $chosen !== '' ? array_filter($boards, static fn (BoardSummary $b) => $b->publicId === $chosen) : $boards;

        $all = $this->cards->openCardsOnBoards(array_keys($shownBoards));
        $ids = array_map(static fn (CardSummary $c) => $c->id, $all);
        $members = $this->cards->membersForCards($ids);
        $labels = $this->labels->forCards($ids);
        $filter = CardFilter::fromInput(static fn (string $key) => $request->input($key));
        $now = $this->clock->now();
        $rows = array_values(array_filter($all, static fn (CardSummary $c) => $filter->matches($c, $labels[$c->id] ?? [], $members[$c->id] ?? [], $user->id, $now)));

        $lists = [];
        foreach ($shownBoards as $board) {
            foreach ($this->directory->openLists($board->id) as $list) {
                $lists[$list->id] = $list->name;
            }
        }

        return $this->render('workspaces/cards', [
            'title'     => $workspace->name,
            'workspace' => $workspace,
            'boards'    => $boards,
            'chosen'    => $chosen,
            'rows'      => $rows,
            'total'     => count($all),
            'lists'     => $lists,
            'members'   => $members,
            'labels'    => $labels,
            'people'    => $this->users->findMany(array_values(array_unique(array_merge([], ...array_values($members))))),
            'filter'    => $filter,
        ]);
    }

    /** Query: days (7, 30 or 90). @param array{workspace: string} $params */
    public function report(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $workspace = $this->workspace($user->id, $params['workspace']);
        [$report, $people, $days] = $this->buildReport($request, $user->id, $workspace);

        return $this->render('workspaces/report', [
            'title'     => $workspace->name,
            'workspace' => $workspace,
            'report'    => $report,
            'people'    => $people,
            'days'      => $days,
            'periods'   => self::PERIODS,
        ]);
    }

    /** @param array{workspace: string} $params */
    public function reportCsv(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $workspace = $this->workspace($user->id, $params['workspace']);
        [$report, $people, $days] = $this->buildReport($request, $user->id, $workspace);
        $t = fn (string $text): string => $this->view->t($text);

        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [$t('Team report'), $workspace->name, $report->from, $report->to], escape: '');
        fputcsv($out, [], escape: '');
        fputcsv($out, [$t('Person'), $t('Open'), $t('Overdue'), $t('Due in the next 7 days'), $t('Completed in the period'), $t('Things done in the period')], escape: '');
        foreach ($report->perPerson as $userId => $row) {
            fputcsv($out, array_map(BoardCsv::safe(...), [$people[$userId]->name ?? '?', (string) $row['open'], (string) $row['overdue'], (string) $row['dueSoon'], (string) $row['completed'], (string) $row['actions']]), escape: '');
        }
        fputcsv($out, [], escape: '');
        fputcsv($out, [$t('Board'), $t('Open'), $t('Done'), $t('Overdue'), $t('Progress')], escape: '');
        foreach ($report->perBoard as $row) {
            fputcsv($out, array_map(BoardCsv::safe(...), [$row['board']->name, (string) $row['open'], (string) $row['done'], (string) $row['overdue'], TeamReport::percent($row['done'], $row['open']) . '%']), escape: '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return (new Response($csv))
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="report-' . $workspace->publicId . '-' . $days . 'd.csv"')
            ->withHeader('Cache-Control', 'no-store');
    }

    /** @return array{0: TeamReport, 1: array<int, \App\Modules\Identity\Contract\User>, 2: int} */
    private function buildReport(Request $request, int $userId, WorkspaceSummary $workspace): array
    {
        $days = in_array((int) $request->input('days'), self::PERIODS, true) ? (int) $request->input('days') : 30;
        $now = $this->clock->now();
        $from = $now->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
        $to = $now->format('Y-m-d');
        $boards = $this->visibleBoards($userId, $workspace);
        $ids = array_keys($boards);

        $open = $this->cards->openCardsOnBoards($ids, 5000);
        $completed = $this->cards->cardsBetween($ids, 'completed_at', $from, $to);
        $created = $this->cards->cardsBetween($ids, 'created_at', $from, $to);
        $members = $this->cards->membersForCards(array_values(array_unique(array_map(static fn (CardSummary $c) => $c->id, [...$open, ...$completed]))));
        $actions = $this->activity->countsByActor($ids, $from, $to);

        $report = TeamReport::build(array_values($boards), $open, $members, $created, $completed, $actions, $now, $days);

        return [$report, $this->users->findMany(array_keys($report->perPerson)), $days];
    }

    private function workspace(int $userId, string $publicId): WorkspaceSummary
    {
        return $this->workspaces->findForUser($publicId, $userId) ?? throw HttpException::notFound();
    }

    /** @return array<int, BoardSummary> board id => board, open boards I can see */
    private function visibleBoards(int $userId, WorkspaceSummary $workspace): array
    {
        $boards = [];
        foreach ($this->boards->boardsInWorkspace($userId, $workspace->id) as $board) {
            $boards[$board->id] = $board;
        }

        return $boards;
    }
}
