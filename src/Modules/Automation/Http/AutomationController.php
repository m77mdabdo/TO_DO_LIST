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
            'failures'    => $this->automations->lastFailures($user->id, $board),
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

    /** The form to change an automation, filled in with what it does now. @param array{automation: string} $params */
    public function edit(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $automation = $this->automations->manageable($user->id, $params['automation']);

        return $this->editPage($user->id, $automation, self::formValues($automation), null);
    }

    /**
     * Without confirm=1, shows what the changes would do (before and after)
     * and saves nothing; with it, saves them.
     *
     * @param array{automation: string} $params
     */
    public function update(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $automation = $this->automations->manageable($user->id, $params['automation']);
        $input = $request->body();
        $editPath = '/automations/' . $automation->publicId . '/edit';

        try {
            if ($request->input('confirm') !== '1') {
                return $this->editPage($user->id, $automation, $input, $this->automations->preview($user->id, $automation->publicId, $input));
            }
            $this->automations->update($user->id, $automation->publicId, $input);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $editPath);
        }

        return $this->redirect($this->boardPath($automation->boardId) . '/automation', 'Automation updated.');
    }

    /** @param array{automation: string} $params */
    public function duplicate(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $automation = $this->automations->manageable($user->id, $params['automation']);
        $back = $this->boardPath($automation->boardId) . '/automation';

        try {
            $copy = $this->automations->duplicate($user->id, $automation->publicId);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $back);
        }

        return $this->redirect('/automations/' . $copy->publicId . '/edit', 'Automation copied. The copy is paused until you turn it on.');
    }

    /**
     * @param array<string, mixed> $values
     * @param array{automation: \App\Modules\Automation\Contract\Automation, before: string, after: string}|null $preview
     */
    private function editPage(int $userId, \App\Modules\Automation\Contract\Automation $automation, array $values, ?array $preview): Response
    {
        $board = $this->board($userId, $this->boards->find($automation->boardId)?->publicId ?? '');

        return $this->render('automation/edit', [
            'title'      => $board->name,
            'board'      => $board,
            'role'       => $this->gate->roleOn($userId, $board->id) ?? BoardRole::Viewer,
            'automation' => $automation,
            'values'     => $values,
            'preview'    => $preview,
            'options'    => $this->automations->options($board, $userId),
            'bodyClass'  => 'page-board-view board-' . $board->color,
        ]);
    }

    /** The automation as the form's fields. @return array<string, mixed> */
    private static function formValues(\App\Modules\Automation\Contract\Automation $a): array
    {
        $join = static fn (string $type, ?string $value): string => $value !== null && $value !== '' ? $type . ':' . $value : $type;

        return [
            'name'       => $a->name,
            'trigger'    => $a->trigger !== null ? $join($a->trigger, $a->triggerValue) : '',
            'scope'      => $a->scopeListId !== null ? (string) $a->scopeListId : '',
            'every'      => (string) ($a->schedule['every'] ?? 'day'),
            'weekday'    => ($a->schedule['every'] ?? '') === 'week' ? (string) ($a->schedule['day'] ?? 0) : '0',
            'monthday'   => ($a->schedule['every'] ?? '') === 'month' ? (string) ($a->schedule['day'] ?? 1) : '1',
            'hour'       => (string) ($a->schedule['hour'] ?? 8),
            'when'       => (string) ($a->schedule['when'] ?? 'before'),
            'days'       => (string) ($a->schedule['days'] ?? 1),
            'conditions' => array_map(static fn (array $c) => $join($c['type'], $c['value']), $a->conditions),
            'actions'    => array_map(static fn (array $x) => ['do' => $join($x['type'], $x['value']), 'text' => $x['text']], $a->actions),
        ];
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
