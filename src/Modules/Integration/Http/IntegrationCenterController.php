<?php

declare(strict_types=1);

namespace App\Modules\Integration\Http;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Integration\Application\Integrations;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

/** The workspace's integration center: connect, change, rotate, test and disconnect ready-made integrations. */
final class IntegrationCenterController extends Controller
{
    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private Integrations $integrations,
        private WorkspaceDirectory $workspaces,
        private UserDirectory $users,
    ) {
        parent::__construct($view, $url, $session);
    }

    /** @param array{workspace: string} $params */
    public function index(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $workspace = $this->workspaces->findForUser($params['workspace'], $user->id) ?? throw AccessDenied::hidden();
        $canManage = $this->integrations->canManage($user->id, $workspace);
        $connected = $canManage ? $this->integrations->forWorkspace($workspace->id) : [];
        $log = $canManage ? $this->integrations->log($workspace->id) : [];

        return $this->render('integrations/center', [
            'title'      => 'Integrations',
            'workspace'  => $workspace,
            'canManage'  => $canManage,
            'types'      => $this->integrations->types(),
            'connected'  => $connected,
            'deliveries' => array_combine(array_column($connected, 'id'), array_map(fn (array $i) => $this->integrations->deliveries($i['id']), $connected)) ?: [],
            'boards'     => $canManage ? $this->integrations->boardsFor($user->id, $workspace) : [],
            'log'        => $log,
            'people'     => $this->users->findMany(array_values(array_filter([...array_column($log, 'actorId'), ...array_column($connected, 'connectedBy')]))),
            'ready'      => $this->integrations->encryptionReady(),
        ]);
    }

    /** Body: type, name, secret, boards[], events[]. @param array{workspace: string} $params */
    public function connect(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $workspace = $this->workspaces->findForUser($params['workspace'], $user->id) ?? throw AccessDenied::hidden();
        $back = '/workspaces/' . $workspace->publicId . '/integrations';
        try {
            $this->integrations->connect($user->id, $workspace, $request->input('type'), $request->input('name'), $request->raw('secret'), $this->list($request, 'boards'), $this->list($request, 'events'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $back . '#connect');
        }

        return $this->redirect($back, 'Connected. Send a test message to check it.');
    }

    /** Body: name, boards[], events[], active. @param array{integration: string} $params */
    public function update(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $integration = $this->integrations->manageable($user->id, $params['integration']);
        try {
            $this->integrations->update($user->id, $integration, $request->input('name'), $this->list($request, 'boards'), $this->list($request, 'events'), $request->input('active') === '1');
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $this->back($user->id, $integration));
        }

        return $this->redirect($this->back($user->id, $integration), 'Integration saved.');
    }

    /** Body: secret. @param array{integration: string} $params */
    public function rotate(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $integration = $this->integrations->manageable($user->id, $params['integration']);
        try {
            $this->integrations->rotate($user->id, $integration, $request->raw('secret'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $this->back($user->id, $integration));
        }

        return $this->redirect($this->back($user->id, $integration), 'The new secret is saved. The old one is no longer used.');
    }

    /** @param array{integration: string} $params */
    public function test(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $integration = $this->integrations->manageable($user->id, $params['integration']);
        $error = $this->integrations->test($user->id, $integration);
        if ($error !== null) {
            $this->view->form->keep(['test_' . $integration['publicId'] => $error], []);

            return $this->redirect($this->back($user->id, $integration));
        }

        return $this->redirect($this->back($user->id, $integration), 'Test message sent.');
    }

    /** @param array{integration: string} $params */
    public function disconnect(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $integration = $this->integrations->manageable($user->id, $params['integration']);
        $this->integrations->disconnect($user->id, $integration);

        return $this->redirect($this->back($user->id, $integration), 'Disconnected. The saved secret was deleted.');
    }

    /** @param array<string, mixed> $integration */
    private function back(int $userId, array $integration): string
    {
        foreach ($this->workspaces->workspacesOf($userId) as $workspace) {
            if ($workspace->id === $integration['workspaceId']) {
                return '/workspaces/' . $workspace->publicId . '/integrations#integration-' . $integration['publicId'];
            }
        }

        return '/boards';
    }

    /** @return list<string> */
    private function list(Request $request, string $key): array
    {
        $value = $request->body()[$key] ?? [];

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }
}
