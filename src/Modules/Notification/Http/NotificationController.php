<?php

declare(strict_types=1);

namespace App\Modules\Notification\Http;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\I18n\Translator;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Notification\Application\NotificationCenter;
use App\Modules\Notification\Contract\Messages;
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
        private Translator $translator,
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

    /**
     * Unread notifications newer than ?after=ID, as plain text, for the
     * browser's own notifications. Only ever the signed-in person's.
     */
    public function latestJson(Request $request): Response
    {
        $user = $this->user($request);
        $after = $request->input('after');
        $after = ctype_digit($after) ? (int) $after : 0;
        $items = array_values(array_filter(
            $this->center->latest($user->id),
            static fn ($n) => $n->id > $after && !$n->isRead(),
        ));
        $people = $this->users->findMany(array_values(array_filter(array_map(static fn ($n) => $n->actorId, $items))));

        $out = [];
        foreach (array_slice($items, 0, 5) as $n) {
            $replace = [
                ':actor'   => $n->actorId !== null ? ($people[$n->actorId]->name ?? $this->translator->t('Someone')) : $this->translator->t('Someone'),
                ':subject' => $n->subject,
            ];
            foreach ($n->data as $key => $value) {
                if ($key !== 'excerpt') {
                    $replace[':' . $key] = $key === 'role' ? $this->translator->t($value) : $value;
                }
            }
            $out[] = [
                'id'   => $n->id,
                'text' => strtr($this->translator->t(Messages::sentence($n->type)), $replace),
                'body' => (string) ($n->data['excerpt'] ?? ''),
                'url'  => $this->url->to($n->link),
            ];
        }

        return Response::json([
            'ok'     => true,
            'latest' => $this->center->latest($user->id)[0]->id ?? 0,
            'unread' => $this->center->unreadCount($user->id),
            'items'  => $out,
        ])->withHeader('Cache-Control', 'no-store');
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
