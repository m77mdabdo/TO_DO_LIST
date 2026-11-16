<?php

declare(strict_types=1);

namespace App\Modules\Personal\Http;

use App\Controllers\Controller;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Personal\Application\CalendarFeed;
use App\Modules\Personal\Application\Planner;
use App\Session\Session;
use App\Shared\Ids\Ulid;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\View\View;
use DateTimeImmutable;

/** The personal week planner and the private calendar (iCal) link. */
final class PlannerController extends Controller
{
    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private Planner $planner,
        private CalendarFeed $feed,
        private Clock $clock,
    ) {
        parent::__construct($view, $url, $session);
    }

    /** Query: week (Y-m-d, any day of the week to show). */
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $from = $this->weekStart($request->input('week'));
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = $from->modify("+$i days")->format('Y-m-d');
        }
        $secret = $this->session->pullFlash('calendar.secret');

        return $this->render('personal/planner', [
            'title'     => 'Planner',
            'days'      => $days,
            'sessions'  => $this->planner->between($user->id, $days[0], $days[6]),
            'unplanned' => $this->planner->unplanned($user->id),
            'previous'  => $from->modify('-7 days')->format('Y-m-d'),
            'next'      => $from->modify('+7 days')->format('Y-m-d'),
            'feed'      => $this->feed->status($user->id),
            'feedUrl'   => is_string($secret) && $secret !== '' ? $this->feed->url($secret) : null,
        ]);
    }

    /** Body: card (public id), day, hour ("" for any time), minutes, back ("card" to return to the card). */
    public function plan(Request $request): Response
    {
        $user = $this->user($request);
        $toCard = $this->cardPath($request);
        try {
            $this->planner->plan($user->id, $request->input('card'), $request->input('day'), $request->input('hour'), $request->input('minutes'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $toCard ?? '/planner');
        }

        return $this->redirect($toCard ?? '/planner?week=' . rawurlencode($request->input('day')), 'Work time planned.');
    }

    /** Body: day, hour, minutes. @param array{id: int} $params */
    public function move(Request $request, array $params): Response
    {
        try {
            $this->planner->move($this->user($request)->id, $params['id'], $request->input('day'), $request->input('hour'), $request->input('minutes'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/planner');
        }

        return $this->redirect('/planner?week=' . rawurlencode($request->input('day')), 'Plan updated.');
    }

    /** Body: week (to come back to). @param array{id: int} $params */
    public function remove(Request $request, array $params): Response
    {
        $this->planner->remove($this->user($request)->id, $params['id']);
        return $this->redirect($this->cardPath($request) ?? '/planner?week=' . rawurlencode($request->input('week')), 'Taken off your plan.');
    }

    public function renewLink(Request $request): Response
    {
        $this->session->flash('calendar.secret', $this->feed->renew($this->user($request)->id));

        return $this->redirect('/planner#calendar-link', 'New calendar link made. The old link no longer works.');
    }

    public function revokeLink(Request $request): Response
    {
        $this->feed->revoke($this->user($request)->id);

        return $this->redirect('/planner#calendar-link', 'Calendar link turned off.');
    }

    /** The feed itself, for calendar apps. No sign-in: the secret in the link is the key. @param array{token: string} $params */
    public function feed(Request $request, array $params): Response
    {
        $userId = $this->feed->owner($params['token']) ?? throw HttpException::notFound();

        return (new Response($this->feed->render($userId, $this->view->t('Due'), $this->view->t('Planned'))))
            ->withHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->withHeader('Content-Disposition', 'inline; filename="todo-board.ics"')
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('X-Robots-Tag', 'noindex');
    }

    /** Back to the card page when the form was sent from there (back=card and a valid card id). */
    private function cardPath(Request $request): ?string
    {
        return $request->input('back') === 'card' && Ulid::isValid($request->input('card')) ? '/cards/' . $request->input('card') . '#planner' : null;
    }

    private function weekStart(string $input): DateTimeImmutable
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $input);
        if ($day === false || $day->format('Y-m-d') !== $input) {
            $day = $this->clock->now()->setTime(0, 0);
        }
        // Arabic weeks start on Saturday, English ones on Monday (as in the timeline).
        $first = $this->view->locale() === 'ar' ? 6 : 1;

        return $day->modify('-' . (((int) $day->format('w') - $first + 7) % 7) . ' days');
    }
}
