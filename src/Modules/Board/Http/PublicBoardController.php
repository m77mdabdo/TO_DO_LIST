<?php

declare(strict_types=1);

namespace App\Modules\Board\Http;

use App\Controllers\Controller;
use App\Core\Config;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Application\BoardService;
use App\Modules\Board\Application\PublicLinks;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Label\Contract\LabelCatalog;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

/** Sharing a board read-only through a secret link, and the page that link opens. */
final class PublicBoardController extends Controller
{
    private const FLASH = 'board.public_link';

    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private BoardService $boards,
        private PublicLinks $links,
        private CardQueries $cards,
        private LabelCatalog $labels,
        private Config $config,
    ) {
        parent::__construct($view, $url, $session);
    }

    /** @param array{board: string} $params */
    public function manage(Request $request, array $params): Response
    {
        $board = $this->boards->boardFor($this->user($request)->id, $params['board'], Permission::ManageSettings);
        $secret = $this->session->pullFlash(self::FLASH);

        return $this->render('boards/share', [
            'title'   => 'Share',
            'board'   => $board,
            'allowed' => $this->links->allowed($board),
            'status'  => $this->links->status($board->id),
            'fresh'   => is_string($secret) ? rtrim((string) $this->config->get('url', ''), '/') . '/shared/' . $secret : null,
        ]);
    }

    /** @param array{board: string} $params */
    public function create(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->boards->boardFor($user->id, $params['board'], Permission::ManageSettings);
        try {
            $this->session->flash(self::FLASH, $this->links->create($user->id, $board));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $this->path($board));
        }

        return $this->redirect($this->path($board), 'Public link made. Anyone with it can view the board.');
    }

    /** @param array{board: string} $params */
    public function revoke(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->boards->boardFor($user->id, $params['board'], Permission::ManageSettings);
        $this->links->revoke($user->id, $board);

        return $this->redirect($this->path($board), 'Public link turned off.');
    }

    /** What the link shows: lists, card titles, labels and dates. No people, comments or files. @param array{token: string} $params */
    public function show(Request $request, array $params): Response
    {
        $board = $this->links->boardFor($params['token']) ?? throw HttpException::notFound();
        $cardsByList = $this->cards->openCardsByList($board->id);
        $cardIds = array_merge([], ...array_map(static fn (array $cards) => array_map(static fn ($c) => $c->id, $cards), array_values($cardsByList)));

        return $this->render('boards/public', [
            'title'       => $board->name,
            'bodyClass'   => 'page-board page-public board-' . $board->color,
            'noindex'     => true,
            'board'       => $board,
            'lists'       => $this->boards->listsOf($board),
            'cardsByList' => $cardsByList,
            'cardLabels'  => $this->labels->forCards($cardIds),
        ])
            ->withHeader('X-Robots-Tag', 'noindex, nofollow')
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('Cache-Control', 'private, no-store');
    }

    private function path(BoardSummary $board): string
    {
        return '/boards/' . $board->publicId . '/share';
    }
}
