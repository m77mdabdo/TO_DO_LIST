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
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Board\Contract\CardFilter;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Label\Contract\LabelCatalog;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Session\Session;
use App\Support\Clock;
use App\Support\Greeting;
use App\Validation\ValidationException;
use App\View\View;

final class BoardController extends Controller
{
    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private BoardService $boards,
        private WorkspaceDirectory $workspaces,
        private AccessGate $gate,
        private CardQueries $cards,
        private LabelCatalog $labels,
        private ActivityFeed $activity,
        private UserDirectory $users,
        private Greeting $greeting,
        private Clock $clock,
    ) {
        parent::__construct($view, $url, $session);
    }

    /** Workspace home: the boards of the current workspace. */
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $workspace = $this->workspaces->current($user->id) ?? throw HttpException::notFound();

        return $this->render('boards/index', [
            'title'     => 'Boards',
            'workspace' => $workspace,
            'boards'    => $this->boards->boardsInWorkspace($user->id, $workspace->id),
            'archived'  => $this->boards->boardsInWorkspace($user->id, $workspace->id, archived: true),
            'greeting'  => $this->greeting->forNow(),
            'user'      => $user,
        ]);
    }

    public function store(Request $request): Response
    {
        $user = $this->user($request);
        $workspace = $this->workspaces->current($user->id) ?? throw HttpException::notFound();

        try {
            $board = $this->boards->createBoard(
                $user->id,
                $workspace->id,
                $request->input('name'),
                BoardVisibility::tryFrom($request->input('visibility')) ?? BoardVisibility::Private,
                $request->input('color', 'violet'),
            );
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/');
        }

        return $this->redirect('/boards/' . $board->publicId);
    }

    /** @param array{board: string} $params */
    public function show(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->boards->boardFor($user->id, $params['board']);
        $role = $this->gate->roleOn($user->id, $board->id);

        // A fixed number of queries however big the board is: lists, cards, labels, activity, people.
        $lists = $this->boards->listsOf($board);
        $cardsByList = $this->cards->openCardsByList($board->id);
        $cardIds = [];
        foreach ($cardsByList as $cards) {
            foreach ($cards as $card) {
                $cardIds[] = $card->id;
            }
        }
        $activity = $role->allows(Permission::ViewActivity) ? $this->activity->forBoard($board->id, null, 15) : [];
        $cardMembers = $this->cards->membersForCards($cardIds);
        $cardLabels = $this->labels->forCards($cardIds);
        $audience = $this->gate->peopleOn($board->id);

        $filter = CardFilter::fromInput(static fn (string $key) => $request->input($key));
        $shown = $filter->apply($cardsByList, $cardLabels, $cardMembers, $user->id, $this->clock->now());

        return $this->render('boards/show', [
            'title'       => $board->name,
            'bodyClass'   => 'page-board board-' . $board->color,
            'board'       => $board,
            'role'        => $role,
            'lists'       => $lists,
            'cardsByList' => $shown,
            'filter'      => $filter,
            'totalCards'  => count($cardIds),
            'shownCards'  => array_sum(array_map('count', $shown)),
            'cardLabels'  => $cardLabels,
            'boardLabels' => $this->labels->forBoard($board->id),
            'activity'    => $activity,
            'cardMembers' => $cardMembers,
            'people'      => $this->users->findMany([...array_keys($audience), ...array_merge([], ...array_values($cardMembers)), ...array_map(static fn ($a) => $a->actorId, $activity)]),
            'audience'    => array_keys($audience),
        ]);
    }

    /** @param array{board: string} $params */
    public function update(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->boards->boardFor($user->id, $params['board'], Permission::ManageSettings);

        try {
            $this->boards->updateBoard($user->id, $board, $request->only(['name', 'description', 'color', 'visibility', 'commenting', 'voting']));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/boards/' . $board->publicId);
        }

        return $this->redirect('/boards/' . $board->publicId, 'Board settings saved.');
    }

    /** @param array{board: string} $params */
    public function archive(Request $request, array $params): Response
    {
        $board = $this->boards->boardFor($this->user($request)->id, $params['board'], Permission::ManageSettings);
        $this->boards->archiveBoard($this->user($request)->id, $board);

        return $this->redirect('/', 'Board archived. You can restore it from the archived boards.');
    }

    /** @param array{board: string} $params */
    public function restore(Request $request, array $params): Response
    {
        $board = $this->boards->boardFor($this->user($request)->id, $params['board'], Permission::ManageSettings);
        $this->boards->archiveBoard($this->user($request)->id, $board, false);

        return $this->redirect('/boards/' . $board->publicId, 'Board restored.');
    }

    /** Archived lists and cards of a board, with restore buttons. */
    public function archived(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->boards->boardFor($user->id, $params['board']);

        return $this->render('boards/archived', [
            'title' => 'Archived items',
            'board' => $board,
            'role'  => $this->gate->roleOn($user->id, $board->id),
            'lists' => $this->boards->archivedLists($user->id, $board),
            'cards' => $this->cards->archivedCards($board->id),
        ]);
    }

    /** CSV of the board's open cards, for spreadsheets. */
    public function export(Request $request, array $params): Response
    {
        $board = $this->boards->boardFor($this->user($request)->id, $params['board'], Permission::Export);
        $cardsByList = $this->cards->openCardsByList($board->id);
        $labels = $this->labels->forCards(array_merge([], ...array_map(
            static fn (array $cards) => array_map(static fn ($c) => $c->id, $cards),
            array_values($cardsByList),
        )));

        $csv = BoardCsv::make($this->boards->listsOf($board), $cardsByList, $labels);
        $filename = 'board-' . preg_replace('/[^A-Za-z0-9]+/', '-', $board->publicId) . '.csv';

        return (new Response($csv))
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public static function boardPath(BoardSummary $board): string
    {
        return '/boards/' . $board->publicId;
    }
}
