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
use App\Shared\Search\SearchSources;
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
        private SearchSources $sources,
    ) {
        parent::__construct($view, $url, $session);
    }

    /**
     * Query: q, board (public id, optional), archived=1 to include the archive.
     * Looks in card titles and descriptions, comments, checklists and
     * attachments, but only on boards this user can see.
     */
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $workspace = $this->workspaces->current($user->id) ?? throw HttpException::notFound();
        $q = mb_substr($request->input('q'), 0, 100);
        $withArchive = $request->input('archived') === '1';

        $visible = [...$this->boards->boardsInWorkspace($user->id, $workspace->id), ...($withArchive ? $this->boards->boardsInWorkspace($user->id, $workspace->id, true) : [])];
        $byId = [];
        foreach ($visible as $board) {
            $byId[$board->id] = $board;
        }
        $only = $request->input('board');
        $scope = $only !== '' ? array_filter($byId, static fn (BoardSummary $b) => $b->publicId === $only) : $byId;

        $searching = mb_strlen(trim($q)) >= 2;
        $hits = $searching ? $this->sources->search(array_keys($scope), trim($q)) : [];
        $cards = array_values(array_filter(
            $this->cards->findMany(array_keys($hits)),
            static fn ($card) => $withArchive || !$card->archived,
        ));
        usort($cards, static fn ($a, $b) => array_search($a->id, array_keys($hits), true) <=> array_search($b->id, array_keys($hits), true));

        return $this->render('search/index', [
            'title'       => 'Search',
            'q'           => $q,
            'searching'   => $searching,
            'workspace'   => $workspace,
            'boards'      => $searching ? array_values(array_filter($scope, static fn (BoardSummary $b) => mb_stripos($b->name, trim($q)) !== false)) : [],
            'cards'       => array_slice($cards, 0, 50),
            'hits'        => $hits,
            'boardById'   => $byId,
            'boardChoices' => array_values(array_filter($byId, static fn (BoardSummary $b) => !$b->archived)),
            'only'        => $only,
            'withArchive' => $withArchive,
        ]);
    }
}
