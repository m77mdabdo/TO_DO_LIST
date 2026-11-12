<?php

declare(strict_types=1);

namespace App\Modules\Notification\Http;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Notification\Application\NotificationCenter;
use App\Session\Session;
use App\View\View;

final class NotificationController extends Controller
{
    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private NotificationCenter $center,
        private UserDirectory $users,
        private BoardDirectory $boards,
    ) {
        parent::__construct($view, $url, $session);
    }

    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $before = $request->input('before');
        $notifications = $this->center->latest($user->id, ctype_digit($before) ? (int) $before : null);
        $actors = array_values(array_filter(array_map(static fn ($n) => $n->actorId, $notifications)));

        return $this->render('notifications/index', [
            'title'         => 'Notifications',
            'notifications' => $notifications,
            'hasMore'       => count($notifications) === NotificationCenter::PAGE,
            'people'        => $this->users->findMany($actors),
            'emailEnabled'  => $this->center->emailEnabled($user->id),
            'unread'        => $this->center->unreadCount($user->id),
        ]);
    }

    /** @param array{id: string} $params */
    public function open(Request $request, array $params): Response
    {
        return $this->redirect($this->center->open($this->user($request)->id, (int) $params['id']));
    }

    public function readAll(Request $request): Response
    {
        $this->center->markAllRead($this->user($request)->id);

        return $this->redirect('/notifications', 'All caught up.');
    }

    public function settings(Request $request): Response
    {
        $this->center->setEmailEnabled($this->user($request)->id, $request->input('email') === '1');

        return $this->redirect('/notifications', 'Settings saved.');
    }

    /** @param array{card: string} $params */
    public function watchCard(Request $request, array $params): Response
    {
        $card = $this->center->cardByPublicId($params['card']) ?? throw AccessDenied::hidden();
        $watching = $this->center->toggleCard($this->user($request)->id, $card);

        return $request->wantsJson()
            ? Response::json(['ok' => true, 'watching' => $watching])
            : $this->redirect('/cards/' . $card->publicId, $watching ? 'You are watching this card.' : 'You stopped watching this card.');
    }

    /** @param array{board: string} $params */
    public function watchBoard(Request $request, array $params): Response
    {
        $board = $this->boards->findByPublicId($params['board']) ?? throw AccessDenied::hidden();
        $watching = $this->center->toggleBoard($this->user($request)->id, $board);

        return $this->redirect('/boards/' . $board->publicId, $watching ? 'You are watching this board.' : 'You stopped watching this board.');
    }
}
