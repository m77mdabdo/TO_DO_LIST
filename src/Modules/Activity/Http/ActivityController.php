<?php

declare(strict_types=1);

namespace App\Modules\Activity\Http;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Activity\Contract\ActivityFeed;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Identity\Contract\UserDirectory;
use App\Session\Session;
use App\View\View;

final class ActivityController extends Controller
{
    public const PAGE = 30;

    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private ActivityFeed $activity,
        private BoardDirectory $boards,
        private AccessGate $gate,
        private UserDirectory $users,
    ) {
        parent::__construct($view, $url, $session);
    }

    /**
     * The whole history of a board, newest first, a page at a time.
     * Query: before (id of the last entry already seen).
     *
     * @param array{board: string} $params
     */
    public function board(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->boards->findByPublicId($params['board']) ?? throw AccessDenied::hidden();
        $this->gate->authorize($user->id, $board->id, Permission::ViewActivity);

        $before = $request->input('before');
        $entries = $this->activity->forBoard($board->id, ctype_digit($before) ? (int) $before : null, self::PAGE);

        return $this->render('activity/board', [
            'title'   => 'Activity',
            'board'   => $board,
            'entries' => $entries,
            'hasMore' => count($entries) === self::PAGE,
            'people'  => $this->users->findMany(array_map(static fn ($entry) => $entry->actorId, $entries)),
        ]);
    }
}
