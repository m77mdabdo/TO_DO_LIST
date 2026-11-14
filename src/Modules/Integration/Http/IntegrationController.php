<?php

declare(strict_types=1);

namespace App\Modules\Integration\Http;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Integration\Application\ApiTokens;
use App\Modules\Integration\Application\Webhooks;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

/** The pages for API tokens (per person) and webhooks (per board). */
final class IntegrationController extends Controller
{
    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private ApiTokens $tokens,
        private Webhooks $webhooks,
        private BoardDirectory $boards,
        private WorkspaceDirectory $workspaces,
        private AccessGate $gate,
    ) {
        parent::__construct($view, $url, $session);
    }

    public function tokens(Request $request): Response
    {
        $user = $this->user($request);
        $boards = [];
        foreach ($this->workspaces->workspacesOf($user->id) as $workspace) {
            foreach ($this->boards->allInWorkspace($workspace->id) as $board) {
                if (!$board->archived && $this->gate->can($user->id, $board->id, Permission::ViewBoard)) {
                    $boards[$board->id] = $board;
                }
            }
        }
        $secret = $this->session->pullFlash('api_token');

        return $this->render('integrations/tokens', [
            'title'   => 'API tokens',
            'tokens'  => $this->tokens->forUser($user->id),
            'boards'  => $boards,
            'secret'  => is_string($secret) ? $secret : null,
            'expiry'  => ApiTokens::EXPIRY_DAYS,
        ]);
    }

    /** Body: name, scope, boards[], days. */
    public function createToken(Request $request): Response
    {
        $user = $this->user($request);
        $boards = $request->body()['boards'] ?? [];

        try {
            [, $secret] = $this->tokens->create($user->id, $request->input('name'), $request->input('scope'), is_array($boards) ? array_map('strval', $boards) : [], (int) $request->input('days', '90'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/integrations#new');
        }
        // Shown once, on the next page only.
        $this->session->flash('api_token', $secret);

        return $this->redirect('/integrations', 'Token created. Copy it now: it will not be shown again.');
    }

    /** @param array{token: string} $params */
    public function revokeToken(Request $request, array $params): Response
    {
        $this->tokens->revoke($this->user($request)->id, $params['token']);

        return $this->redirect('/integrations', 'Token revoked.');
    }

    /** @param array{board: string} $params */
    public function webhooks(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->board($user->id, $params['board']);
        $hooks = $this->webhooks->forBoard($user->id, $board);
        $deliveries = [];
        foreach ($hooks as $hook) {
            $deliveries[$hook->id] = $this->webhooks->deliveries($hook);
        }

        return $this->render('integrations/webhooks', [
            'title'      => $board->name,
            'board'      => $board,
            'role'       => $this->gate->roleOn($user->id, $board->id),
            'webhooks'   => $hooks,
            'deliveries' => $deliveries,
            'bodyClass'  => 'page-board-view board-' . $board->color,
        ]);
    }

    /** Body: url, events[]. @param array{board: string} $params */
    public function createWebhook(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->board($user->id, $params['board']);
        $events = $request->body()['events'] ?? [];

        try {
            $this->webhooks->create($user->id, $board, $request->input('url'), is_array($events) ? array_map('strval', $events) : []);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/boards/' . $board->publicId . '/webhooks#new');
        }

        return $this->redirect('/boards/' . $board->publicId . '/webhooks', 'Webhook added.');
    }

    /** Body: on. @param array{webhook: string} $params */
    public function toggleWebhook(Request $request, array $params): Response
    {
        $on = $request->input('on') === '1';
        $hook = $this->webhooks->setEnabled($this->user($request)->id, $params['webhook'], $on);

        return $this->redirect($this->boardPath($hook->boardId) . '/webhooks', $on ? 'Webhook turned on.' : 'Webhook paused.');
    }

    /** @param array{webhook: string} $params */
    public function deleteWebhook(Request $request, array $params): Response
    {
        $hook = $this->webhooks->delete($this->user($request)->id, $params['webhook']);

        return $this->redirect($this->boardPath($hook->boardId) . '/webhooks', 'Webhook deleted.');
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
