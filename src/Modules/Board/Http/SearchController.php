<?php

declare(strict_types=1);

namespace App\Modules\Board\Http;

use App\Controllers\Controller;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Board\Application\BoardService;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Session\Session;
use App\View\View;

/**
 * Searches the boards and cards of the current workspace. Only boards the
 * user can see are searched, so results never reveal a private board.
 */
final class SearchController extends Controller
{
    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private BoardService $boards,
        private WorkspaceDirectory $workspaces,
        private CardQueries $cards,
    ) {
        parent::__construct($view, $url, $session);
    }

    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $workspace = $this->workspaces->current($user->id) ?? throw HttpException::notFound();
        $q = mb_substr($request->input('q'), 0, 100);

        $visible = $this->boards->boardsInWorkspace($user->id, $workspace->id);
        $byId = [];
        foreach ($visible as $board) {
            $byId[$board->id] = $board;
        }

        $searching = mb_strlen($q) >= 2;

        return $this->render('search/index', [
            'title'     => 'Search',
            'q'         => $q,
            'searching' => $searching,
            'workspace' => $workspace,
            'boards'    => $searching ? array_values(array_filter($visible, static fn (BoardSummary $b) => mb_stripos($b->name, $q) !== false)) : [],
            'cards'     => $searching ? $this->cards->search(array_keys($byId), $q) : [],
            'boardById' => $byId,
        ]);
    }
}
