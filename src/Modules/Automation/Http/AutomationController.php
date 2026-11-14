<?php

declare(strict_types=1);

namespace App\Modules\Automation\Http;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Access\Contract\Permission;
use App\Modules\Automation\Application\AutomationService;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Label\Contract\LabelCatalog;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

final class AutomationController extends Controller
{
    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private AutomationService $automations,
        private BoardDirectory $boards,
        private CardQueries $cards,
        private LabelCatalog $labels,
        private AccessGate $gate,
        private UserDirectory $users,
    ) {
        parent::__construct($view, $url, $session);
    }

    /** The board's automations, the form to add one, and the run log. Managers only. @param array{board: string} $params */
    public function index(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->board($user->id, $params['board']);
        $automations = $this->automations->forBoard($user->id, $board);

        $lists = array_column(array_map(static fn ($l) => ['id' => $l->id, 'name' => $l->name], $this->boards->openLists($board->id)), 'name', 'id');
        $labels = array_column(array_map(static fn ($l) => ['id' => $l->id, 'name' => $l->name], $this->labels->forBoard($board->id)), 'name', 'id');
        $runs = $this->automations->runLog($user->id, $board);
        $people = $this->users->findMany(array_values(array_unique([
            ...array_keys($this->gate->peopleOn($board->id)),
            ...array_map(static fn ($a) => $a->createdBy, $automations),
            ...array_filter(array_map(static fn ($r) => $r->actorId, $runs)),
        ])));
        $names = array_map(static fn ($p) => $p->name, $people);

        $described = [];
        foreach ($automations as $automation) {
            $described[$automation->id] = $this->automations->describe($automation, $lists, $labels, $names);
        }

        return $this->render('automation/index', [
            'title'       => $board->name,
            'board'       => $board,
            'role'        => $this->gate->roleOn($user->id, $board->id) ?? BoardRole::Viewer,
            'automations' => $automations,
            'described'   => $described,
            'runs'        => $runs,
            'cardTitles'  => array_map(static fn ($c) => $c->title, $this->cards->findMany(array_values(array_filter(array_map(static fn ($r) => $r->cardId, $runs))))),
            'people'      => $people,
            'options'     => $this->automations->options($board, $user->id),
            'kind'        => in_array($request->input('kind'), \App\Modules\Automation\Contract\Automation::KINDS, true) ? $request->input('kind') : 'rule',
            'bodyClass'   => 'page-board-view board-' . $board->color,
        ]);
    }

    /** @param array{board: string} $params */
    public function store(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->board($user->id, $params['board']);
        $back = '/boards/' . $board->publicId . '/automation';

        try {
            $this->automations->create($user->id, $board, $request->body());
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $back . '?kind=' . rawurlencode($request->input('kind')) . '#new');
        }

        return $this->redirect($back, 'Automation saved.');
    }

    /** Body: on (1 or 0). @param array{automation: string} $params */
    public function toggle(Request $request, array $params): Response
    {
        $on = $request->input('on') === '1';
        $automation = $this->automations->setEnabled($this->user($request)->id, $params['automation'], $on);

        return $this->redirect($this->boardPath($automation->boardId) . '/automation', $on ? 'Automation turned on.' : 'Automation paused.');
    }

    /** @param array{automation: string} $params */
    public function destroy(Request $request, array $params): Response
    {
        $automation = $this->automations->delete($this->user($request)->id, $params['automation']);

        return $this->redirect($this->boardPath($automation->boardId) . '/automation', 'Automation deleted.');
    }

    /** A card button. @param array{card: string, automation: string} $params */
    public function pressCard(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $card = $this->cards->findByPublicId($params['card']) ?? throw AccessDenied::hidden();
        if (!$this->gate->can($user->id, $card->boardId, Permission::ViewBoard)) {
            throw AccessDenied::hidden();
        }

        $error = $this->automations->pressCard($user->id, $params['automation'], $card);
        if ($error !== null) {
            $this->view->form->keep(['automation' => $error], []);

            return Response::redirect($this->url->to('/cards/' . $card->publicId));
        }

        return $this->redirect('/cards/' . $card->publicId, 'Done.');
    }

    /** A board button. @param array{automation: string} $params */
    public function pressBoard(Request $request, array $params): Response
    {
        $result = $this->automations->pressBoard($this->user($request)->id, $params['automation']);
        $path = $this->boardPath($result['button']->boardId);
        if ($result['failed'] > 0) {
            $this->view->form->keep(['automation' => $this->view->t('Worked on :ok, failed on :failed: :reason', [
                'ok'     => (string) $result['ok'],
                'failed' => (string) $result['failed'],
                'reason' => $this->view->t((string) $result['error']),
            ])], []);

            return Response::redirect($this->url->to($path));
        }

        return $this->redirect($path, $this->view->t('Done on :count card(s) or list(s).', ['count' => (string) $result['ok']]));
    }

    private function board(int $userId, string $publicId): BoardSummary
    {
        $board = $this->boards->findByPublicId($publicId);
        if ($board === null || !$this->gate->can($userId, $board->id, Permission::ViewBoard)) {
            throw AccessDenied::hidden();
        }
        $this->gate->authorize($userId, $board->id, Permission::ManageSettings);

        return $board;
    }

    private function boardPath(int $boardId): string
    {
        return '/boards/' . ($this->boards->find($boardId)?->publicId ?? '');
    }
}
